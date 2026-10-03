"""Reject unsafe or incomplete customer packages before any release is created."""

import io
from pathlib import Path
import sys
import tarfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
import plugin_package


class PackageTests(unittest.TestCase):
    def setUp(self):
        self.files = dict.fromkeys([
            "index.php", "includes/github-config.php", "includes/github-updater.php",
            "includes/github-helpers.php", "admin/admin-integration.php",
            "css/wpiko-chatbot-pro.css", "js/contact-form.js", "pwa/index.html",
            "pwa/manifest.json", "pwa/service-worker.js", "pwa/icons/icon-192.png",
            "pwa/icons/icon-512.png",
        ], b"")
        self.files["wpiko-chatbot-pro.php"] = b"""<?php
/**
 * Version: 2.0.9
 */
define('WPIKO_CHATBOT_PRO_VERSION', '2.0.9');
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-config.php';
"""
        self.notes = b"# Changelog\n\n## 2.0.9\n\n* Improved Pro feature.\n\n## 2.0.8\n\n* Older change.\n"
        self.link = False

    def git(self, command, *args):
        if command == "show":
            return self.notes
        if command != "archive":
            raise AssertionError(command)
        output = io.BytesIO()
        with tarfile.open(fileobj=output, mode="w") as archive:
            for name, content in self.files.items():
                entry = tarfile.TarInfo(name)
                entry.size = len(content)
                archive.addfile(entry, io.BytesIO(content))
            if self.link:
                entry = tarfile.TarInfo("includes/unsafe.php")
                entry.type = tarfile.SYMTYPE
                entry.linkname = "/outside/plugin"
                archive.addfile(entry)
        return output.getvalue()

    def verify(self):
        with patch.object(plugin_package, "git", side_effect=self.git):
            return plugin_package.verify()

    def test_mismatched_version_stops_packaging(self):
        self.files["wpiko-chatbot-pro.php"] = self.files["wpiko-chatbot-pro.php"].replace(
            b"PRO_VERSION', '2.0.9'", b"PRO_VERSION', '2.0.8'")
        with self.assertRaisesRegex(ValueError, "must match"):
            self.verify()

    def test_missing_runtime_file_stops_packaging(self):
        del self.files["includes/github-updater.php"]
        with self.assertRaisesRegex(ValueError, "Missing runtime files"):
            self.verify()

    def test_development_or_credentials_cannot_ship(self):
        for name in [".github/workflows/publish-release.yml", "docs/publishing-steps.md", ".env", "backup.key"]:
            with self.subTest(file=name):
                self.files[name] = b"not for customers"
                with self.assertRaisesRegex(ValueError, "Development or private file"):
                    self.verify()
                del self.files[name]

    def test_symlink_cannot_ship(self):
        self.link = True
        with self.assertRaisesRegex(ValueError, "Unexpected package entry"):
            self.verify()

    def test_missing_or_duplicate_release_notes_stop_packaging(self):
        for notes in [b"## 2.0.8\n* Old notes", b"## 2.0.9\n* One\n## 2.0.9\n* Two"]:
            self.notes = notes
            with self.assertRaisesRegex(ValueError, "exactly one"):
                self.verify()

    def test_php_syntax_error_stops_packaging(self):
        self.files["includes/github-updater.php"] = b"<?php function broken( {"
        with self.assertRaisesRegex(ValueError, "PHP syntax failed"):
            self.verify()

    def test_release_notes_exclude_previous_versions(self):
        with patch.object(plugin_package, "git", side_effect=self.git):
            self.assertEqual(plugin_package.release_notes("HEAD", "2.0.9"), "* Improved Pro feature.")


if __name__ == "__main__":
    unittest.main()
