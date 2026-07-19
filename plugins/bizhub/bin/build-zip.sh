#!/usr/bin/env bash
#
# Build a WordPress-installable release ZIP for the BizHub plugin.
#
# Archives the current HEAD commit (respecting .gitattributes export-ignore
# rules to drop dev-only files), installs production-only Composer
# dependencies into that tree, then zips it up with "bizhub" as the
# top-level folder so it can be extracted straight into
# wp-content/plugins/.
#
# BizHub may live at the root of its own standalone repo, or nested
# inside the Bizplugins monorepo at plugins/bizhub/ (with no .git of
# its own). Either way, `git rev-parse --show-prefix` from this
# script's own directory tells us the path from the repo root down to
# here, so `git archive` can be run from the repo root with that path
# as a pathspec and the result stripped back down to this subtree via
# `tar --strip-components`, instead of always archiving the whole
# repo, which would otherwise pull in the other two plugins too.
# (`git archive HEAD:$PREFIX` looks like the obvious alternative, but
# the <rev>:<path> syntax resolves <path> relative to the *current*
# directory, not the repo root, so it silently doubles the prefix and
# archives nothing when run from inside a subdirectory - hence the
# pathspec + strip-components approach instead.)
#
# Usage: bin/build-zip.sh [version]
#   version defaults to the "Version:" header in bizhub.php.

set -euo pipefail

cd "$(dirname "$0")/.."

REPO_ROOT="$(git rev-parse --show-toplevel)"
PREFIX="$(git rev-parse --show-prefix)"
STRIP_COMPONENTS=0
if [ -n "$PREFIX" ]; then
    STRIP_COMPONENTS=$(grep -o "/" <<< "$PREFIX" | wc -l)
fi

VERSION="${1:-$(grep -m1 '^ \* Version:' bizhub.php | sed -E 's/.*Version:[[:space:]]*//')}"

if [ -z "$VERSION" ]; then
    echo "Could not determine plugin version; pass one explicitly: bin/build-zip.sh 1.2.3" >&2
    exit 1
fi

echo "Building bizhub-${VERSION}.zip ..."

rm -rf build
mkdir -p build/bizhub

if [ -n "$PREFIX" ]; then
    (cd "$REPO_ROOT" && git archive --format=tar HEAD -- "$PREFIX") | tar -x -C build/bizhub --strip-components="$STRIP_COMPONENTS"
else
    git archive --format=tar HEAD | tar -x -C build/bizhub
fi

(
    cd build/bizhub
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    rm -f composer.json composer.lock
)

(
    cd build
    rm -f "bizhub-${VERSION}.zip"

    if command -v zip >/dev/null 2>&1; then
        zip -r -q "bizhub-${VERSION}.zip" bizhub
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
' "bizhub-${VERSION}.zip" bizhub
    else
        echo "Neither zip nor python3 is available to create the archive." >&2
        exit 1
    fi
)

echo "Built build/bizhub-${VERSION}.zip"
