#!/bin/sh
# Build a zip package of the plugin (UNIX / WSL / macOS)
# Usage: cd build && ./build.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ZIP_NAME="seo-page-monitor-$(date +%Y%m%d%H%M).zip"
ZIP_PATH="$PLUGIN_DIR/../$ZIP_NAME"

cd "$PLUGIN_DIR" || exit 1
# Exclude common dev folders from archive
zip -r "$ZIP_PATH" . \
  -x "build/*" \
  -x "node_modules/*" \
  -x ".git/*" \
  -x ".github/*" \
  -x "tests/*" > /dev/null
if [ $? -eq 0 ]; then
  echo "Created $ZIP_PATH"
else
  echo "Failed to create package"
  exit 1
fi
