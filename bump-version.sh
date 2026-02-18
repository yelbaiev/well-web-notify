#!/bin/bash
# ============================================
# Well Web — Auto Version Bump Script
# ============================================
# Increments the patch version in index.php
# Semver rules:
#   1.0.0 → 1.0.1
#   1.0.9 → 1.1.0
#   1.9.9 → 2.0.0
#
# Usage: ./bump-version.sh [path-to-index.php]
# If no path given, uses ./index.php

set -e

INDEX_FILE="${1:-./index.php}"

if [ ! -f "$INDEX_FILE" ]; then
    echo "Error: $INDEX_FILE not found"
    exit 1
fi

# Extract current version from plugin header " * Version: X.Y.Z"
CURRENT=$(grep -m1 'Version:' "$INDEX_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)

if [ -z "$CURRENT" ]; then
    echo "Error: Could not find Version in $INDEX_FILE"
    exit 1
fi

# Split into major.minor.patch
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT"

# Increment with carry-over (each segment maxes at 9)
PATCH=$((PATCH + 1))
if [ "$PATCH" -gt 9 ]; then
    PATCH=0
    MINOR=$((MINOR + 1))
fi
if [ "$MINOR" -gt 9 ]; then
    MINOR=0
    MAJOR=$((MAJOR + 1))
fi

NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"

echo "Bumping version: $CURRENT → $NEW_VERSION"

# Update plugin header: " * Version: X.Y.Z"
sed -i '' "s|Version: ${CURRENT}|Version: ${NEW_VERSION}|" "$INDEX_FILE"

# Update define constant: define( 'WELLWEB_*_VERSION', 'X.Y.Z' );
sed -i '' "s|_VERSION', '${CURRENT}'|_VERSION', '${NEW_VERSION}'|" "$INDEX_FILE"

# Verify changes
VERIFY=$(grep -m1 'Version:' "$INDEX_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
if [ "$VERIFY" != "$NEW_VERSION" ]; then
    echo "Error: Version update failed. Expected $NEW_VERSION, got $VERIFY"
    exit 1
fi

echo "Done: $NEW_VERSION"
echo "$NEW_VERSION"
