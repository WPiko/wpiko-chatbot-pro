#!/bin/bash
# Releases are managed by the GitHub Actions publishing workflow.
set -e
cat <<'INSTRUCTIONS'
Publish Pro releases through GitHub Actions:
1. Update the plugin version and CHANGELOG.md, then commit and push to main.
2. Open https://github.com/WPiko/wpiko-chatbot-pro/actions/workflows/publish-release.yml
3. Click Run workflow, select main, and leave Test only unchecked to publish.

This script does not change files, push tags, or publish releases.
INSTRUCTIONS
