#!/bin/sh
# Local mirror of .github/workflows/dist-qa.yml.
#
# Runs the SAME php-lint + phpcs (Magento2) + phpmd checks CI runs on a pull request,
# against the PHP files changed on this branch, so a red gate is caught before pushing.
# It installs the same tool versions CI uses (magento/magento-coding-standard:^40,
# phpmd/phpmd:^2.15) into a local, git-ignored .ci-tools directory.
#
# Usage:
#   .github/scripts/dist-qa-local.sh [base-ref]
#
# base-ref defaults to the dist-2.4.x branch inferred from the current branch name,
# falling back to dist-2.4.9. Unlike CI (which only sees committed changes), this also
# includes staged, unstaged and untracked PHP files so the check is useful before commit.
set -eu

cd "$(git rev-parse --show-toplevel)"

BASE="${1:-}"
if [ -z "$BASE" ]; then
    BASE=$(git rev-parse --abbrev-ref HEAD | sed -n 's#.*\(2\.4\.[0-9][0-9]*\).*#dist-\1#p')
    [ -z "$BASE" ] && BASE="dist-2.4.9"
fi
if ! git rev-parse --verify --quiet "$BASE" >/dev/null 2>&1; then
    echo "Base ref '$BASE' not found. Pass it explicitly: dist-qa-local.sh <base-ref>" >&2
    exit 2
fi

echo "Base ref: $BASE"

# .github/ is excluded: it carries CI tooling inherited from upstream (CLI scripts that
# legitimately use echo/exit), which the Magento2 standard rejects and which is not part
# of the distributed code.
CHANGED=$(
    {
        git diff --name-only --diff-filter=ACMR "$BASE"...HEAD -- '*.php' ':(exclude).github/'
        git diff --name-only --diff-filter=ACMR -- '*.php' ':(exclude).github/'
        git diff --name-only --diff-filter=ACMR --cached -- '*.php' ':(exclude).github/'
        git ls-files --others --exclude-standard -- '*.php' ':(exclude).github/'
    } 2>/dev/null | sort -u | grep -v '^$' || true
)

if [ -z "$CHANGED" ]; then
    echo "No changed PHP files. Nothing to check."
    exit 0
fi

echo "Changed PHP files:"
echo "$CHANGED" | sed 's/^/  /'

if [ ! -x .ci-tools/vendor/bin/phpcs ] || [ ! -x .ci-tools/vendor/bin/phpmd ]; then
    echo "Installing QA tools into .ci-tools ..."
    mkdir -p .ci-tools
    (
        cd .ci-tools
        [ -f composer.json ] || composer init --no-interaction --name=jeanmarcos/dist-qa-tools >/dev/null
        composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true >/dev/null
        composer require --no-interaction magento/magento-coding-standard:^40 phpmd/phpmd:^2.15
    )
fi

status=0

echo
echo "== PHP lint =="
OLDIFS=$IFS
IFS='
'
for f in $CHANGED; do
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "  FAIL $f"
        php -l "$f" 2>&1 | sed 's/^/    /'
        status=1
    fi
done
IFS=$OLDIFS
[ "$status" -eq 0 ] && echo "  ok"

echo
echo "== phpcs (Magento2) =="
# shellcheck disable=SC2086
if .ci-tools/vendor/bin/phpcs --standard=Magento2 -p $CHANGED; then
    echo "  ok"
else
    status=1
fi

echo
echo "== phpmd (production files only) =="
PHPMD_LIST=$(echo "$CHANGED" | grep -v '/Test/' | paste -sd, -)
if [ -z "$PHPMD_LIST" ]; then
    echo "  no non-test PHP files changed; skipping"
elif .ci-tools/vendor/bin/phpmd "$PHPMD_LIST" text .github/phpmd-ruleset.xml; then
    echo "  ok"
else
    status=1
fi

echo
if [ "$status" -eq 0 ]; then
    echo "dist QA (local): PASS"
else
    echo "dist QA (local): FAIL"
fi
exit "$status"
