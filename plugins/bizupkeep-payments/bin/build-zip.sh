#!/usr/bin/env bash
#
# Build a WordPress-installable release ZIP for the BizUpKeep Payments
# plugin. Mirrors bizupkeep-workflow/bin/build-zip.sh exactly - see
# that script's comments for why config/ is kept in the release
# (RoleGrant `require`s config/permissions.php at runtime) and why
# this copies the working tree rather than using `git archive`.
#
# Usage: bin/build-zip.sh [version]
#   version defaults to the "Version:" header in bizupkeep-payments.php.

set -euo pipefail

cd "$(dirname "$0")/.."

VERSION="${1:-$(grep -m1 '^ \* Version:' bizupkeep-payments.php | sed -E 's/.*Version:[[:space:]]*//')}"

if [ -z "$VERSION" ]; then
    echo "Could not determine plugin version; pass one explicitly: bin/build-zip.sh 1.0.0" >&2
    exit 1
fi

echo "Building bizupkeep-payments-${VERSION}.zip ..."

rm -rf build
mkdir -p build/bizupkeep-payments

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
    rsync -a "${RSYNC_EXCLUDES[@]}" ./ build/bizupkeep-payments/
else
    # Fallback: copy each top-level entry individually (excluding
    # "build" itself, which would otherwise copy into itself), then
    # remove any other excluded paths from the copy.
    shopt -s dotglob
    for entry in ./*; do
        name="$(basename "$entry")"
        if [ "$name" = "build" ]; then
            continue
        fi
        cp -r "$entry" "build/bizupkeep-payments/$name"
    done
    shopt -u dotglob

    for item in "${EXCLUDES[@]}"; do
        rm -rf "build/bizupkeep-payments/${item}"
    done
fi

(
    cd build/bizupkeep-payments

    # Drop require-dev/repositories before installing: the declared
    # repositories are dev-only path-repositories pointing at sibling
    # BizHub-ecosystem checkouts (for phpstan/tests), which do not
    # exist relative to this staged copy. Composer validates declared
    # repositories eagerly even when nothing in a --no-dev install
    # actually needs them, so a stale/missing composer.lock makes this
    # fail on a fresh checkout unless those keys are removed first.
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
    rm -f "bizupkeep-payments-${VERSION}.zip"

    if command -v zip >/dev/null 2>&1; then
        zip -r -q "bizupkeep-payments-${VERSION}.zip" bizupkeep-payments
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
' "bizupkeep-payments-${VERSION}.zip" bizupkeep-payments
    else
        echo "Neither zip nor python3 is available to create the archive." >&2
        exit 1
    fi
)

echo "Built build/bizupkeep-payments-${VERSION}.zip"
