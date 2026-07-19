#!/usr/bin/env bash
#
# Build a WordPress-installable release ZIP for the BizUpKeep Workflow
# plugin.
#
# Unlike BizHub's equivalent script, this does NOT use `git archive`:
# copies the working tree directly (no commit is required), dropping
# dev-only files, installs production-only Composer dependencies into
# that copy, then zips it up with "bizupkeep-workflow" as the
# top-level folder so it can be extracted straight into
# wp-content/plugins/.
#
# Deliberately keeps config/ in the release (unlike BizHub's build
# script, which excludes it): BizUpKeep Workflow's RoleGrant and
# WorkflowNotificationListener classes `require` files from
# config/permissions.php and config/notifications.php at runtime, so
# dropping that directory would fatal on a fresh install.
#
# Usage: bin/build-zip.sh [version]
#   version defaults to the "Version:" header in bizupkeep-workflow.php.

set -euo pipefail

cd "$(dirname "$0")/.."

VERSION="${1:-$(grep -m1 '^ \* Version:' bizupkeep-workflow.php | sed -E 's/.*Version:[[:space:]]*//')}"

if [ -z "$VERSION" ]; then
    echo "Could not determine plugin version; pass one explicitly: bin/build-zip.sh 1.0.0" >&2
    exit 1
fi

echo "Building bizupkeep-workflow-${VERSION}.zip ..."

rm -rf build
mkdir -p build/bizupkeep-workflow

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
    rsync -a "${RSYNC_EXCLUDES[@]}" ./ build/bizupkeep-workflow/
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
        cp -r "$entry" "build/bizupkeep-workflow/$name"
    done
    shopt -u dotglob

    for item in "${EXCLUDES[@]}"; do
        rm -rf "build/bizupkeep-workflow/${item}"
    done
fi

(
    cd build/bizupkeep-workflow
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    rm -f composer.json composer.lock
)

(
    cd build
    rm -f "bizupkeep-workflow-${VERSION}.zip"

    if command -v zip >/dev/null 2>&1; then
        zip -r -q "bizupkeep-workflow-${VERSION}.zip" bizupkeep-workflow
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
' "bizupkeep-workflow-${VERSION}.zip" bizupkeep-workflow
    else
        echo "Neither zip nor python3 is available to create the archive." >&2
        exit 1
    fi
)

echo "Built build/bizupkeep-workflow-${VERSION}.zip"
