#!/usr/bin/env bash
#
# Build a WordPress-installable release ZIP for the BizUpKeep
# Bookkeeping plugin. Mirrors bizupkeep-workflow/bin/build-zip.sh
# exactly - see that script's comments for why config/ is kept in the
# release (RoleGrant `require`s config/permissions.php at runtime) and
# why this copies the working tree rather than using `git archive`.
#
# Usage: bin/build-zip.sh [version]
#   version defaults to the "Version:" header in bizupkeep-bookkeeping.php.

set -euo pipefail

cd "$(dirname "$0")/.."

VERSION="${1:-$(grep -m1 '^ \* Version:' bizupkeep-bookkeeping.php | sed -E 's/.*Version:[[:space:]]*//')}"

if [ -z "$VERSION" ]; then
    echo "Could not determine plugin version; pass one explicitly: bin/build-zip.sh 1.0.0" >&2
    exit 1
fi

echo "Building bizupkeep-bookkeeping-${VERSION}.zip ..."

rm -rf build
mkdir -p build/bizupkeep-bookkeeping

EXCLUDES=(
    ".git" ".github" ".claude" ".phpstan" ".phpunit.result.cache"
    "docs" "tests" "bin" "build" "vendor"
    ".editorconfig" ".gitattributes" ".gitignore"
    "phpunit.xml" "phpstan.neon" "phpcs.xml" "phpcs.xml.dist"
    "CONTRIBUTING.md" "CODEOWNERS"
)

RSYNC_EXCLUDES=()
for item in "${EXCLUDES[@]}"; do
    RSYNC_EXCLUDES+=(--exclude "$item")
done

if command -v rsync >/dev/null 2>&1; then
    rsync -a "${RSYNC_EXCLUDES[@]}" ./ build/bizupkeep-bookkeeping/
else
    shopt -s dotglob
    for entry in ./*; do
        name="$(basename "$entry")"
        if [ "$name" = "build" ]; then
            continue
        fi
        cp -r "$entry" "build/bizupkeep-bookkeeping/$name"
    done
    shopt -u dotglob

    for item in "${EXCLUDES[@]}"; do
        rm -rf "build/bizupkeep-bookkeeping/${item}"
    done
fi

(
    cd build/bizupkeep-bookkeeping

    php -r '
        $composer = json_decode(file_get_contents("composer.json"), true);
        unset($composer["repositories"], $composer["require-dev"]);
        file_put_contents("composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    '
    rm -f composer.lock

    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    rm -f composer.json composer.lock
)

(
    cd build
    rm -f "bizupkeep-bookkeeping-${VERSION}.zip"

    if command -v zip >/dev/null 2>&1; then
        zip -r -q "bizupkeep-bookkeeping-${VERSION}.zip" bizupkeep-bookkeeping
    elif command -v python3 >/dev/null 2>&1; then
        python3 -c '
import pathlib
import sys
import zipfile

zip_name, root = sys.argv[1], pathlib.Path(sys.argv[2])
with zipfile.ZipFile(zip_name, "w", zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(root.rglob("*")):
        if path.is_file():
            archive.write(path, path.relative_to(root.parent))
' "bizupkeep-bookkeeping-${VERSION}.zip" bizupkeep-bookkeeping
    else
        echo "Neither zip nor python3 is available to create the archive." >&2
        exit 1
    fi
)

echo "Built build/bizupkeep-bookkeeping-${VERSION}.zip"
