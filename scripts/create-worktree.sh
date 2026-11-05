#!/usr/bin/env bash

set -u

ZED_MAIN_GIT_WORKTREE=${ZED_MAIN_GIT_WORKTREE:-}
ZED_WORKTREE_ROOT=${ZED_WORKTREE_ROOT:-}

if [ -z "$ZED_MAIN_GIT_WORKTREE" ] || [ -z "$ZED_WORKTREE_ROOT" ]; then
    echo "This file should only be called from Zed create worktree" >&2
    exit 1
fi

copies=(
    ".env"
    ".env.testing"
    "boost.json"
)

links=(
    ".agents/skills"
    ".agents/specs"
)

for file in "${copies[@]}"; do
    [ -f "$ZED_MAIN_GIT_WORKTREE/$file" ] && cp -n "$ZED_MAIN_GIT_WORKTREE/$file" "$ZED_WORKTREE_ROOT/$file"
done

for file in "${links[@]}"; do
    [ -d "$ZED_MAIN_GIT_WORKTREE/$file" ] && ln -s "$ZED_MAIN_GIT_WORKTREE/$file" "$ZED_WORKTREE_ROOT/$file"
done

cd "$ZED_WORKTREE_ROOT" || exit 0

composer install --no-interaction
npm install

php artisan key:generate
php artisan ide-helper:generate
