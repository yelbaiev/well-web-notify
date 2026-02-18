#!/bin/bash
# ============================================
# Well Web — Auto Bump + Commit + Push
# ============================================
# 1. Bumps patch version in index.php
# 2. Stages the version change
# 3. Creates (or amends) a commit with the version bump
# 4. Pushes to remote
#
# Usage:
#   ./push.sh                  # bump + new commit + push
#   ./push.sh "commit message" # bump + commit with message + push
#   ./push.sh --amend          # bump + amend last commit + push

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INDEX_FILE="$SCRIPT_DIR/index.php"

# Run bump
NEW_VERSION=$("$SCRIPT_DIR/bump-version.sh" "$INDEX_FILE" | tail -1)

# Stage the bumped file
cd "$SCRIPT_DIR"
git add index.php

if [ "$1" = "--amend" ]; then
    git commit --amend --no-edit
    echo "Amended last commit with version $NEW_VERSION"
else
    MSG="${1:-Bump version to $NEW_VERSION}"
    git commit -m "$MSG"
    echo "Committed: $MSG"
fi

# Push
BRANCH=$(git rev-parse --abbrev-ref HEAD)
git push origin "$BRANCH"

echo "✅ Pushed $BRANCH with version $NEW_VERSION"
