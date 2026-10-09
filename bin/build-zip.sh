#!/usr/bin/env bash
#
# Build script to generate clean distributable ZIP for WordPress.
#

set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${DIR}/dist"
PLUGIN_SLUG="personal-payment-gateway-for-binance"
TMP_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo "Building ${ZIP_NAME}..."

rm -rf "${DIST_DIR}"
mkdir -p "${TMP_DIR}"

# Copy runtime files
cp -r "${DIR}/binance-personal-pay.php" "${TMP_DIR}/"
cp -r "${DIR}/uninstall.php" "${TMP_DIR}/"
cp -r "${DIR}/readme.txt" "${TMP_DIR}/"
cp -r "${DIR}/README.md" "${TMP_DIR}/"
cp -r "${DIR}/LICENSE" "${TMP_DIR}/"
cp -r "${DIR}/CHANGELOG.md" "${TMP_DIR}/"
cp -r "${DIR}/composer.json" "${TMP_DIR}/"
cp -r "${DIR}/includes" "${TMP_DIR}/"
cp -r "${DIR}/admin" "${TMP_DIR}/"
cp -r "${DIR}/frontend" "${TMP_DIR}/"
cp -r "${DIR}/database" "${TMP_DIR}/"
cp -r "${DIR}/languages" "${TMP_DIR}/"

# Clean up any potential junk
find "${TMP_DIR}" -name ".DS_Store" -delete
find "${TMP_DIR}" -name "*.log" -delete

# Create ZIP archive
cd "${DIST_DIR}"
zip -r -q "${ZIP_NAME}" "${PLUGIN_SLUG}"

# Also keep binance-personal-pay.zip copy for legacy script compatibility
cp "${ZIP_NAME}" "binance-personal-pay.zip"

rm -rf "${TMP_DIR}"

echo "✓ Release build complete: ${DIST_DIR}/${ZIP_NAME}"
echo "✓ Compatibility build: ${DIST_DIR}/binance-personal-pay.zip"
