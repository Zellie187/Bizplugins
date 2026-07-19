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
# Usage: bin/build-zip.sh [version]
#   version defaults to the "Version:" header in bizhub.php.

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

VERSION="${1:-$(grep -m1 '^ \* Version:' bizhub.php | sed -E 's/.*Version:[[:space:]]*//')}"

if [ -z "$VERSION" ]; then
    echo "Could not determine plugin version; pass one explicitly: bin/build-zip.sh 1.2.3" >&2
    exit 1
fi

echo "Building bizhub-${VERSION}.zip ..."

rm -rf build
mkdir -p build/bizhub

git archive --format=tar HEAD | tar -x -C build/bizhub

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
