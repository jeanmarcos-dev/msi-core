#!/bin/sh
set -eu

usage='usage: ci-static-lists.sh <base-commit> <output-dir>'
base=${1-}
out=${2:?$usage}
empty_tree=4b825dc642cb6eb9a060e54bf8d69288fbee4904

case "$base" in
    ''|0000000000000000000000000000000000000000) base=$empty_tree ;;
esac

git diff --name-only --diff-filter=ACMR "$base" HEAD > "$out/changed.txt"
git diff --name-only --diff-filter=A "$base" HEAD > "$out/added.txt"
