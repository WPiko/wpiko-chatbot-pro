#!/usr/bin/env python3
"""Validate a committed Pro plugin and optionally build its installable ZIP."""

import argparse
import io
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[2]
SLUG = "wpiko-chatbot-pro"
VERSION_PATTERN = r"\d+\.\d+\.\d+"


def git(*args):
    return subprocess.check_output(["git", *args], cwd=ROOT)


def version_numbers(version):
    if not re.fullmatch(VERSION_PATTERN, version):
        raise ValueError("Expected a numeric X.Y.Z version, got " + version)
    return tuple(int(part) for part in version.split("."))


def release_notes(ref, version):
    changelog = git("show", ref + ":CHANGELOG.md").decode("utf-8")
    headings = list(re.finditer(r"^## (" + VERSION_PATTERN + r")\s*$", changelog, re.MULTILINE))
    matches = [index for index, heading in enumerate(headings) if heading.group(1) == version]
    if len(matches) != 1:
        raise ValueError("Add exactly one '## " + version + "' entry to CHANGELOG.md")
    index = matches[0]
    end = headings[index + 1].start() if index + 1 < len(headings) else len(changelog)
    notes = changelog[headings[index].end():end].strip()
    if not notes or not re.search(r"^[*-] \S", notes, re.MULTILINE):
        raise ValueError("Add release notes beneath '## " + version + "' in CHANGELOG.md")
    return notes


def verify(ref="HEAD"):
    files = {}
    with tarfile.open(fileobj=io.BytesIO(git("archive", "--format=tar", ref))) as archive:
        for entry in archive.getmembers():
            path = PurePosixPath(entry.name)
            if path.is_absolute() or ".." in path.parts:
                raise ValueError("Unsafe package path: " + entry.name)
            if entry.isdir():
                continue
            if not entry.isfile():
                raise ValueError("Unexpected package entry: " + entry.name)
            files[entry.name] = archive.extractfile(entry).read()

    forbidden = {".git", ".github", ".svn", ".vscode", ".idea", "docs", "tests",
                 "node_modules", "__pycache__"}
    for name in files:
        path = PurePosixPath(name)
        if forbidden.intersection(path.parts) or path.name in {
            "README.md", "CHANGELOG.md", ".gitattributes", ".gitignore", ".DS_Store",
            "release.sh", "check-github-config.php", ".env",
        } or path.name.startswith(".env.") or path.suffix in {
            ".pem", ".key", ".log", ".sql", ".zip", ".bak",
        }:
            raise ValueError("Development or private file in package: " + name)

    required = {SLUG + ".php", "index.php", "includes/github-config.php",
                "includes/github-updater.php", "includes/github-helpers.php",
                "admin/admin-integration.php", "css/wpiko-chatbot-pro.css",
                "js/contact-form.js", "pwa/index.html", "pwa/manifest.json",
                "pwa/service-worker.js", "pwa/icons/icon-192.png", "pwa/icons/icon-512.png"}
    plugin = files.get(SLUG + ".php", b"").decode("utf-8")
    required.update(re.findall(r"WPIKO_CHATBOT_PRO_PATH\s*\.\s*['\"]([^'\"]+)['\"]", plugin))
    missing = required - files.keys()
    if missing:
        raise ValueError("Missing runtime files: " + ", ".join(sorted(missing)))

    header = re.search(r"^\s*\*\s*Version:\s*(\S+)\s*$", plugin, re.MULTILINE)
    constant = re.search(r"define\(\s*['\"]WPIKO_CHATBOT_PRO_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", plugin)
    if not header or not constant or header.group(1) != constant.group(1):
        raise ValueError("The plugin header and WPIKO_CHATBOT_PRO_VERSION must match")
    version = header.group(1)
    version_numbers(version)
    notes = release_notes(ref, version)
    if not shutil.which("php"):
        raise ValueError("PHP CLI is required to verify the plugin")
    php_files = sorted(name for name in files if name.endswith(".php"))
    with tempfile.TemporaryDirectory(prefix="wpiko-pro-check-") as temp:
        for name in php_files:
            path = Path(temp) / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(files[name])
            result = subprocess.run(["php", "-l", str(path)], text=True,
                                    stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
            if result.returncode:
                raise ValueError("PHP syntax failed for " + name + "\n" + result.stdout)
    print("Version and changelog verified: " + version)
    print("PHP syntax passed: " + str(len(php_files)) + " files")
    print("Package verified: " + str(len(files)) + " runtime files")
    return version, notes


def build(ref, output_dir):
    version, notes = verify(ref)
    output = Path(output_dir).resolve()
    output.mkdir(parents=True, exist_ok=True)
    package = output / (SLUG + "-" + version + ".zip")
    subprocess.run(["git", "archive", "--format=zip", "--prefix=" + SLUG + "/",
                    "--output=" + str(package), ref], cwd=ROOT, check=True)
    if os.environ.get("GITHUB_OUTPUT"):
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as stream:
            stream.write("version=" + version + "\nzip_path=" + str(package) + "\n")
    print("Installable ZIP: " + str(package))
    return version, notes, package


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ref", default="HEAD")
    parser.add_argument("--output-dir")
    args = parser.parse_args()
    try:
        if args.output_dir:
            build(args.ref, args.output_dir)
        else:
            verify(args.ref)
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        parser.exit(1, "Plugin verification failed: " + str(error) + "\n")
