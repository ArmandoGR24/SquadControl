#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_DIR="$ROOT_DIR/release"
PACKAGE_DIR="$RELEASE_DIR/squadcontrol"
ZIP_PATH="$RELEASE_DIR/squadcontrol-release.zip"
TOKEN_PATH="$RELEASE_DIR/installer-token.txt"

cd "$ROOT_DIR"

if [[ ! -f "vendor/autoload.php" ]]; then
  echo "Installing PHP dependencies..."
  composer install --no-dev --optimize-autoloader
fi

echo "Building frontend..."
npm ci
npm run build

rm -rf "$PACKAGE_DIR" "$ZIP_PATH" "$TOKEN_PATH"
mkdir -p "$PACKAGE_DIR"

rsync -a \
  --exclude ".git" \
  --exclude ".github" \
  --exclude "node_modules" \
  --exclude "tests" \
  --exclude "release" \
  --exclude "storage/logs/*" \
  --exclude "storage/framework/cache/*" \
  --exclude "storage/framework/sessions/*" \
  --exclude "storage/framework/views/*" \
  "$ROOT_DIR/" "$PACKAGE_DIR/"

mkdir -p "$PACKAGE_DIR/storage/framework/cache" "$PACKAGE_DIR/storage/framework/sessions" "$PACKAGE_DIR/storage/framework/views" "$PACKAGE_DIR/storage/logs"

INSTALLER_TOKEN="$(php -r 'echo rtrim(strtr(base64_encode(random_bytes(24)), "+/", "-_"), "=");')"
INSTALLER_TOKEN_HASH="$(php -r '$t = $argv[1]; echo hash("sha256", $t);' "$INSTALLER_TOKEN")"
printf "%s" "$INSTALLER_TOKEN_HASH" > "$PACKAGE_DIR/storage/framework/install_token.hash"

cat > "$TOKEN_PATH" <<EOF
Instalador SquadControl

Token de instalacion (un solo uso):
$INSTALLER_TOKEN

Usa esta URL: https://tu-dominio.com/install.php?token=$INSTALLER_TOKEN
EOF

cd "$RELEASE_DIR"
zip -qr "$(basename "$ZIP_PATH")" "squadcontrol"

echo "Release created: $ZIP_PATH"
echo "Installer token saved in: $TOKEN_PATH"
