#!/usr/bin/env bash
#
# License Bridge for ProfilePress build script.
#
# Zips the seller-side plugin into:
#   ../build/license-bridge-for-profilepress/license-bridge-for-profilepress-{version}.zip
#
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PLUGIN_DIR="$SCRIPT_DIR"
PLUGIN_SLUG="$( basename "$PLUGIN_DIR" )"
PLUGINS_DIR="$( cd "$PLUGIN_DIR/.." && pwd )"
BUILD_DIR="${PLUGINS_DIR}/build/${PLUGIN_SLUG}"

cd "$PLUGIN_DIR"

VERSION=$(
  grep -E '^\s*\*\s*Version:' license-bridge-for-profilepress.php \
    | head -n1 \
    | sed -E 's/^[^0-9]*([0-9][0-9A-Za-z.\-]*).*$/\1/'
)

if [[ -z "${VERSION}" ]]; then
  echo "Could not determine plugin version from license-bridge-for-profilepress.php header." >&2
  exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
mkdir -p "$BUILD_DIR"
ZIP_PATH="${BUILD_DIR}/${ZIP_NAME}"

rm -f "$ZIP_PATH"

echo "Building ${PLUGIN_SLUG} v${VERSION}"
echo "Packaging -> ${ZIP_PATH}"

cd "$PLUGINS_DIR"

zip -rq "$ZIP_PATH" "$PLUGIN_SLUG" \
  -x "${PLUGIN_SLUG}/.git/*" \
  -x "${PLUGIN_SLUG}/.github/*" \
  -x "${PLUGIN_SLUG}/.gitignore" \
  -x "${PLUGIN_SLUG}/graphify-out/*" \
  -x "${PLUGIN_SLUG}/CLAUDE.md" \
  -x "${PLUGIN_SLUG}/README.md" \
  -x "${PLUGIN_SLUG}/LICENSE" \
  -x "${PLUGIN_SLUG}/.DS_Store" \
  -x "${PLUGIN_SLUG}/**/.DS_Store" \
  -x "${PLUGIN_SLUG}/build.sh" \
  -x "${PLUGIN_SLUG}/*.zip" \
  -x "${PLUGIN_SLUG}/.*" \
  -x "${PLUGIN_SLUG}/**/.*"

SIZE=$( du -h "$ZIP_PATH" | awk '{print $1}' )
echo "Built ${ZIP_NAME} (${SIZE})"
