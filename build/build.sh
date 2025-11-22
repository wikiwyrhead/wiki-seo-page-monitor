#!/bin/sh
# Build a zip package of the plugin (UNIX / WSL / macOS)
# Usage: cd build && ./build.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ZIP_NAME="seo-page-monitor-$(date +%Y%m%d%H%M).zip"
ZIP_PATH="$PLUGIN_DIR/../$ZIP_NAME"

cd "$PLUGIN_DIR" || exit 1
# Exclude non-install assets from archive (keep vendor included)
zip -r "$ZIP_PATH" . \
  -x "build/*" \
  -x "node_modules/*" \
  -x ".git/*" \
  -x ".github/*" \
  -x "tests/*" \
  -x ".gitattributes" \
  -x ".gitignore" \
  -x ".phpunit.result.cache" \
  -x "composer.json" \
  -x "composer.lock" \
  -x "package.json" \
  -x "package-lock.json" \
  -x "phpunit.xml" \
  -x "webpack.config.js" \
  -x "GOOGLE_SHEETS.md" \
  -x "IMPROVEMENTS.md" \
  -x "INSTALLATION.md" \
  -x "SECURITY.md" \
  -x "*.DS_Store" > /dev/null
if [ $? -eq 0 ]; then
  echo "Created $ZIP_PATH"
else
  echo "Failed to create package"
  exit 1
fi
