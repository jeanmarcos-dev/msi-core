#!/bin/sh
set -eu

usage='usage: ci-static-changed.sh <magento-dir> <changed-list> <added-list>'
magento=${1:?$usage}
changed=${2:?$usage}
added=${3:?$usage}
modules=$magento/app/code/Magento
tests=$magento/dev/tests/static/testsuite/Magento/Test

module_files() {
    while IFS= read -r file; do
        module=${file%%/*}
        [ -f "$modules/$module/registration.php" ] && echo "app/code/Magento/$file"
    done < "$1" || true
}

for directory in "$tests/_files" "$tests/Less/_files" "$tests/Html/_files"; do
    rm -f "$directory"/changed_files*
    module_files "$changed" > "$directory/changed_files_msi-core.txt"
done
module_files "$added" > "$tests/_files/changed_files_msi-core.added.txt"
