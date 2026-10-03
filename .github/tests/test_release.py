"""Exercise release failure paths without writing to GitHub."""

import hashlib
import io
import importlib.util
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

SCRIPTS = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS))
spec = importlib.util.spec_from_file_location("publish_release", SCRIPTS / "publish-release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        stdout = patch("sys.stdout", new=io.StringIO())
        stdout.start()
        self.addCleanup(stdout.stop)
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.package = Path(self.temp.name) / "wpiko-chatbot-pro-2.0.9.zip"
        self.package.write_bytes(b"verified package contents")
        self.digest = "sha256:" + hashlib.sha256(self.package.read_bytes()).hexdigest()
        self.commit = "a" * 40
        self.latest = {"tag_name": "v2.0.8"}
        self.reference = None
        self.draft = None
        self.calls = []
        self.api = Mock()
        self.api.request.side_effect = self.request

    def request(self, path, method="GET", data=None, **kwargs):
        self.calls.append((method, path, data, kwargs))
        if path == "releases/latest":
            return self.latest
        if path == "branches/main":
            return {"commit": {"sha": self.commit}}
        if path == "git/ref/tags/v2.0.9":
            return self.reference
        if path == "releases/tags/v2.0.9":
            return self.draft
        if path == "git/refs" and method == "POST":
            self.reference = {"object": {"type": "commit", "sha": data["sha"]}}
            return self.reference
        if path == "releases" and method == "POST":
            self.draft = {"id": 123, "draft": True, "assets": [], "tag_name": "v2.0.9"}
            return self.draft
        if path.startswith("releases/123/assets?") and method == "POST":
            asset = {"name": self.package.name, "state": "uploaded", "digest": self.digest}
            self.draft["assets"].append(asset)
            return asset
        if path == "releases/123" and method == "PATCH":
            return {"draft": False, "tag_name": "v2.0.9", "html_url": "https://github.com/WPiko/wpiko-chatbot-pro/releases/tag/v2.0.9"}
        raise AssertionError("Unexpected API request: " + method + " " + path)

    def publish(self, dry_run=False):
        release.publish(self.api, "2.0.9", "* Improved Pro feature.", self.package, self.commit, dry_run)

    def writes(self):
        return [call for call in self.calls if call[0] != "GET"]

    def test_dry_run_never_mutates_even_for_existing_version(self):
        self.latest = {"tag_name": "v2.0.9"}
        self.publish(dry_run=True)
        self.assertEqual(self.writes(), [])

    def test_existing_version_and_downgrade_stop_before_writes(self):
        for version in ["v2.0.9", "v2.0.10"]:
            with self.subTest(latest=version):
                self.latest = {"tag_name": version}
                with self.assertRaisesRegex(ValueError, "newer"):
                    self.publish()
                self.assertEqual(self.writes(), [])

    def test_conflicting_tag_is_never_replaced(self):
        self.reference = {"object": {"type": "commit", "sha": "b" * 40}}
        with self.assertRaisesRegex(ValueError, "another commit"):
            self.publish()
        self.assertEqual(self.writes(), [])

    def test_stale_main_stops_before_writes(self):
        with self.assertRaisesRegex(ValueError, "main has changed"):
            release.publish(self.api, "2.0.9", "* Notes", self.package, "b" * 40, False)
        self.assertEqual(self.writes(), [])

    def test_published_release_is_never_replaced(self):
        self.reference = {"object": {"type": "commit", "sha": self.commit}}
        self.draft = {"draft": False}
        with self.assertRaisesRegex(ValueError, "already published"):
            self.publish()
        self.assertEqual(self.writes(), [])

    def test_success_attaches_zip_before_publication(self):
        self.publish()
        writes = self.writes()
        self.assertEqual([call[1] for call in writes], [
            "git/refs", "releases", "releases/123/assets?name=" + self.package.name, "releases/123"])
        self.assertEqual(writes[0][2], {"ref": "refs/tags/v2.0.9", "sha": self.commit})
        self.assertTrue(writes[1][2]["draft"])
        self.assertFalse(writes[-1][2]["draft"])
        self.assertEqual(writes[-1][2]["make_latest"], "true")

    def test_retry_reuses_matching_tag_draft_and_zip(self):
        self.reference = {"object": {"type": "commit", "sha": self.commit}}
        self.draft = {"id": 123, "draft": True, "assets": [
            {"name": self.package.name, "state": "uploaded", "digest": self.digest}]}
        self.publish()
        self.assertEqual([call[0] for call in self.writes()], ["PATCH"])

    def test_wrong_draft_zip_is_never_overwritten_or_published(self):
        self.reference = {"object": {"type": "commit", "sha": self.commit}}
        self.draft = {"id": 123, "draft": True, "assets": [
            {"name": self.package.name, "state": "uploaded", "digest": "sha256:wrong"}]}
        with self.assertRaisesRegex(ValueError, "not overwritten"):
            self.publish()
        self.assertEqual(self.writes(), [])

    def test_upload_failure_keeps_release_as_draft(self):
        def fail_upload(path, *args, **kwargs):
            if "/assets?" in path:
                raise OSError("Upload interrupted")
            return self.request(path, *args, **kwargs)
        self.api.request.side_effect = fail_upload
        with self.assertRaisesRegex(OSError, "interrupted"):
            self.publish()
        self.assertTrue(self.draft["draft"])
        self.assertFalse(any(call[0] == "PATCH" for call in self.calls))

    def test_newer_release_during_upload_prevents_publication(self):
        def concurrent_release(path, *args, **kwargs):
            result = self.request(path, *args, **kwargs)
            if "/assets?" in path:
                self.latest = {"tag_name": "v2.0.10"}
            return result
        self.api.request.side_effect = concurrent_release
        with self.assertRaisesRegex(ValueError, "newer"):
            self.publish()
        self.assertFalse(any(call[0] == "PATCH" for call in self.calls))

    def test_failed_lookup_cannot_publish(self):
        self.api.request.side_effect = OSError("GitHub unavailable")
        with self.assertRaises(OSError):
            self.publish()
        self.assertEqual(self.writes(), [])

    def test_annotated_tag_resolves_to_commit(self):
        api = Mock()
        api.request.return_value = {"object": {"type": "commit", "sha": self.commit}}
        self.assertEqual(release.tag_commit(api, {"object": {"type": "tag", "sha": "c" * 40}}), self.commit)

    def test_invalid_version_is_rejected_before_requests(self):
        with self.assertRaises(ValueError):
            release.publish(self.api, "2.0.9; invalid", "* Notes", self.package, self.commit, False)
        self.api.request.assert_not_called()

    def test_malformed_latest_version_is_rejected(self):
        self.latest = {"tag_name": "vunknown"}
        with self.assertRaises(ValueError):
            self.publish()
        self.assertEqual(self.writes(), [])


if __name__ == "__main__":
    unittest.main()
