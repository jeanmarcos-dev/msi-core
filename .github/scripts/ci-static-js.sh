#!/bin/sh
set -eu

usage='usage: ci-static-js.sh <magento-dir> <file-list>'
magento=${1:?$usage}
list=${2:?$usage}
modules=$magento/app/code/Magento

set --
while IFS= read -r file; do
    case "$file" in
        *.js) [ -f "$modules/${file%%/*}/registration.php" ] && set -- "$@" "app/code/Magento/$file" ;;
    esac
done < "$list"

if [ "$#" -eq 0 ]; then
    echo "no module JavaScript to lint"
    exit 0
fi

cd "$magento"
cp package.json.sample package.json
npm install --no-audit --no-fund

exec npx eslint -c vendor/magento/magento-coding-standard/eslint/eslint.config.mjs "$@"
