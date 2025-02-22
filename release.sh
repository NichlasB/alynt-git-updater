#!/bin/bash

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Please provide a version number (e.g. ./release.sh 1.2.3)"
    exit 1
fi

# Find the main plugin file (the one with Plugin Name in the header)
PLUGIN_FILE=$(grep -l "Plugin Name:" *.php | head -n 1)

if [ -z "$PLUGIN_FILE" ]; then
    echo "Error: Could not find main plugin file"
    exit 1
fi

# Extract the plugin constant prefix from the file (e.g., ALYNT_GIT from ALYNT_GIT_VERSION)
PREFIX=$(grep -o "[A-Z_]\+_VERSION" "$PLUGIN_FILE" | head -n 1 | sed 's/_VERSION//')

if [ -z "$PREFIX" ]; then
    echo "Error: Could not find version constant prefix"
    exit 1
fi

echo "Found plugin file: $PLUGIN_FILE"
echo "Found constant prefix: $PREFIX"

# Update version in main plugin file
sed -i "s/Version: .*$/Version: $VERSION/" "$PLUGIN_FILE"
sed -i "s/define('${PREFIX}_VERSION'.*$/define('${PREFIX}_VERSION', '$VERSION');/" "$PLUGIN_FILE"

# Update readme.txt
sed -i "s/Stable tag: .*$/Stable tag: $VERSION/" readme.txt

# Commit changes
git add .
git commit -m "Release version $VERSION"

# Create and push tag
git tag -a "v$VERSION" -m "Version $VERSION"
git push && git push --tags