#!/usr/bin/env python3
"""Publish a verified Pro release only after its ZIP is attached to a draft."""

import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
from urllib.error import HTTPError
from urllib.parse import quote
from urllib.request import Request, urlopen

from plugin_package import build, git, version_numbers

# The source repository may be renamed (the public name stays as a bridge for old installs).
REPOSITORIES = ("WPiko/wpiko-chatbot-pro", "WPiko/wpiko-chatbot-pro-source")
REPOSITORY = REPOSITORIES[0]


class GitHub:
    def __init__(self):
        repository = os.environ.get("GITHUB_REPOSITORY", REPOSITORY)
        if repository.lower() not in [name.lower() for name in REPOSITORIES]:
            raise ValueError("This workflow belongs to " + " or ".join(REPOSITORIES))
        self.repository = repository
        self.token = os.environ.get("GH_TOKEN")

    def request(self, path, method="GET", data=None, missing_ok=False, upload=False):
        if method != "GET" and not self.token:
            raise ValueError("Publishing requires a GitHub Actions token with contents: write")
        host = "uploads.github.com" if upload else "api.github.com"
        headers = {"Accept": "application/vnd.github+json", "User-Agent": "WPiko-Pro-Release",
                   "X-GitHub-Api-Version": "2022-11-28"}
        if self.token:
            headers["Authorization"] = "Bearer " + self.token
        if upload:
            headers["Content-Type"] = "application/octet-stream" if str(path).endswith(".sig") else "application/zip"
            body = data
        elif data is not None:
            headers["Content-Type"] = "application/json"
            body = json.dumps(data).encode()
        else:
            body = None
        request = Request("https://" + host + "/repos/" + self.repository + "/" + path,
                          data=body, headers=headers, method=method)
        try:
            with urlopen(request, timeout=90) as response:
                return json.load(response)
        except HTTPError as error:
            if missing_ok and method == "GET" and error.code == 404:
                return None
            raise ValueError("GitHub " + method + " " + path + " failed (HTTP " + str(error.code) + ")") from error


def require_newer(version, latest):
    if latest:
        previous = latest["tag_name"]
        if not previous.startswith("v"):
            raise ValueError("The latest release must use a vX.Y.Z tag")
        if version_numbers(version) <= version_numbers(previous[1:]):
            raise ValueError("Choose a version newer than published release " + previous)


def tag_commit(api, reference):
    object_data = reference["object"]
    for _ in range(10):
        if object_data["type"] == "commit":
            return object_data["sha"]
        if object_data["type"] != "tag":
            break
        object_data = api.request("git/tags/" + object_data["sha"])["object"]
    raise ValueError("Could not resolve the existing version tag to a commit")


def find_release(api, tag):
    # The tag endpoint only promises published releases. Listing with write
    # access also includes drafts, allowing recovery after an interrupted upload.
    page = 1
    while True:
        releases = api.request("releases?per_page=100&page=" + str(page))
        for candidate in releases:
            if candidate["tag_name"] == tag:
                return candidate
        if len(releases) < 100:
            return None
        page += 1


def release_keys(commit):
    """Public release keys built into the plugin at this commit (includes/updater.php)."""
    source = git("show", commit + ":includes/updater.php").decode("utf-8")
    block = re.search(r"const\s+RELEASE_KEYS\s*=\s*array\((.*?)\);", source, re.DOTALL)
    keys = re.findall(r"['\"]([A-Za-z0-9+/]{43}=)['\"]", block.group(1)) if block else []
    if not keys:
        raise ValueError("includes/updater.php has no RELEASE_KEYS")
    return keys


def openssl(*args, data=None):
    return subprocess.run(["openssl", *args], input=data, check=True,
                          stdout=subprocess.PIPE, stderr=subprocess.PIPE).stdout


def sign_package(package, private_key_pem, allowed_keys):
    """Sign the ZIP with the release key (Ed25519) and write <zip>.sig (base64).

    The key must be one of the public keys the plugin checks updates against,
    so a release can never be signed with a key customer sites would reject.
    """
    with tempfile.TemporaryDirectory(prefix="wpiko-sign-") as temp:
        key_file = Path(temp) / "key.pem"
        key_file.write_text(private_key_pem.strip() + "\n")
        os.chmod(key_file, 0o600)
        public_der = openssl("pkey", "-in", str(key_file), "-pubout", "-outform", "DER")
        public_key = base64.b64encode(public_der[-32:]).decode()
        if public_key not in allowed_keys:
            raise ValueError("WPIKO_RELEASE_SIGNING_KEY does not match RELEASE_KEYS in includes/updater.php")
        signature = openssl("pkeyutl", "-sign", "-inkey", str(key_file), "-rawin", "-in", str(package))
        if len(signature) != 64:
            raise ValueError("Unexpected signature length")
        public_file = Path(temp) / "public.pem"
        public_file.write_bytes(openssl("pkey", "-in", str(key_file), "-pubout"))
        sig_file = Path(temp) / "check.sig"
        sig_file.write_bytes(signature)
        openssl("pkeyutl", "-verify", "-pubin", "-inkey", str(public_file), "-rawin",
                "-in", str(package), "-sigfile", str(sig_file))
    signature_path = package.with_name(package.name + ".sig")
    signature_path.write_text(base64.b64encode(signature).decode() + "\n")
    print("Signed with release key " + public_key)
    return signature_path


def attach(api, release, path):
    digest = "sha256:" + hashlib.sha256(path.read_bytes()).hexdigest()
    existing = next((asset for asset in release["assets"] if asset["name"] == path.name), None)
    if existing:
        if existing.get("digest") != digest or existing.get("state") != "uploaded":
            raise ValueError("The draft already has a different or incomplete " + path.name + "; it was not overwritten")
        return
    asset = api.request("releases/" + str(release["id"]) + "/assets?name=" + quote(path.name),
                        "POST", path.read_bytes(), upload=True)
    if asset.get("digest") != digest or asset.get("state") != "uploaded":
        raise ValueError("GitHub did not confirm the uploaded " + path.name + "; the release remains a draft")


def publish(api, version, notes, package, commit, dry_run, signature=None):
    version_numbers(version)
    tag = "v" + version
    latest = api.request("releases/latest", missing_ok=True)
    if dry_run:
        print("Latest published release: " + (latest["tag_name"] if latest else "none"))
        print("Dry run passed: no Git tag, draft, asset, or release was created.")
        return

    require_newer(version, latest)
    if api.request("branches/main")["commit"]["sha"] != commit:
        raise ValueError("main has changed since this run started; run the workflow again on main")
    reference = api.request("git/ref/tags/" + tag, missing_ok=True)
    if reference and tag_commit(api, reference) != commit:
        raise ValueError(tag + " already points to another commit; choose a new version")
    release = find_release(api, tag)
    if release and not release["draft"]:
        raise ValueError(tag + " is already published; published releases are never replaced")
    if release and not reference:
        raise ValueError("An existing draft has no matching Git tag; review it before retrying")
    if not reference:
        api.request("git/refs", "POST", {"ref": "refs/tags/" + tag, "sha": commit})
    if not release:
        release = api.request("releases", "POST", {
            "tag_name": tag, "target_commitish": commit, "name": "Version " + version,
            "body": notes, "draft": True, "prerelease": False,
        })

    attach(api, release, package)
    if signature is not None:
        attach(api, release, signature)

    # Recheck in case another maintainer published a newer release during the upload.
    require_newer(version, api.request("releases/latest", missing_ok=True))
    if tag_commit(api, api.request("git/ref/tags/" + tag)) != commit:
        raise ValueError("The tag changed during upload; the release remains a draft")
    published = api.request("releases/" + str(release["id"]), "PATCH", {
        "name": "Version " + version, "body": notes, "draft": False,
        "prerelease": False, "make_latest": "true",
    })
    if published["draft"] or published["tag_name"] != tag:
        raise ValueError("GitHub did not confirm release publication")
    print("Published " + published["html_url"])


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--output-dir", required=True)
    args = parser.parse_args()
    try:
        commit = git("rev-parse", "HEAD").decode().strip()
        if os.environ.get("GITHUB_SHA", commit) != commit:
            raise ValueError("The checkout must match the triggering commit")
        version, notes, package = build(commit, args.output_dir)
        signing_key = os.environ.get("WPIKO_RELEASE_SIGNING_KEY", "").strip()
        signature = None
        if signing_key:
            signature = sign_package(package, signing_key, release_keys(commit))
            if os.environ.get("GITHUB_OUTPUT"):
                with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as stream:
                    stream.write("sig_path=" + str(signature) + "\n")
        elif not args.dry_run:
            raise ValueError("Add the WPIKO_RELEASE_SIGNING_KEY secret: customer sites refuse unsigned updates")
        else:
            print("Not signed: WPIKO_RELEASE_SIGNING_KEY is not set (a real release would stop here).")
        publish(GitHub(), version, notes, package, commit, args.dry_run, signature)
    except (ValueError, OSError, KeyError, subprocess.CalledProcessError) as error:
        parser.exit(1, "Release failed: " + str(error) + "\n")
