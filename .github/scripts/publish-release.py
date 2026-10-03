#!/usr/bin/env python3
"""Publish a verified Pro release only after its ZIP is attached to a draft."""

import argparse
import hashlib
import json
import os
import subprocess
from urllib.error import HTTPError
from urllib.parse import quote
from urllib.request import Request, urlopen

from plugin_package import build, git, version_numbers

REPOSITORY = "WPiko/wpiko-chatbot-pro"


class GitHub:
    def __init__(self):
        if os.environ.get("GITHUB_REPOSITORY", REPOSITORY).lower() != REPOSITORY.lower():
            raise ValueError("This workflow belongs to " + REPOSITORY)
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
            headers["Content-Type"] = "application/zip"
            body = data
        elif data is not None:
            headers["Content-Type"] = "application/json"
            body = json.dumps(data).encode()
        else:
            body = None
        request = Request("https://" + host + "/repos/" + REPOSITORY + "/" + path,
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


def publish(api, version, notes, package, commit, dry_run):
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
    release = api.request("releases/tags/" + tag, missing_ok=True)
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

    digest = "sha256:" + hashlib.sha256(package.read_bytes()).hexdigest()
    existing = next((asset for asset in release["assets"] if asset["name"] == package.name), None)
    if existing:
        if existing.get("digest") != digest or existing.get("state") != "uploaded":
            raise ValueError("The draft already has a different or incomplete ZIP; it was not overwritten")
    else:
        asset = api.request("releases/" + str(release["id"]) + "/assets?name=" + quote(package.name),
                            "POST", package.read_bytes(), upload=True)
        if asset.get("digest") != digest or asset.get("state") != "uploaded":
            raise ValueError("GitHub did not confirm the uploaded ZIP; the release remains a draft")

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
        publish(GitHub(), version, notes, package, commit, args.dry_run)
    except (ValueError, OSError, KeyError, subprocess.CalledProcessError) as error:
        parser.exit(1, "Release failed: " + str(error) + "\n")
