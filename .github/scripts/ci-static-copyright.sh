#!/bin/sh
set -eu

usage='usage: ci-static-copyright.sh <base-commit> <changed-list> <added-list>'
base=${1:?$usage}
changed=${2:?$usage}
added=${3:?$usage}

adobe='Copyright (© )?([0-9]{4}(-present)? )?(Adobe|Magento, Inc)'
own_owner='Copyright [0-9]{4} Jeanmarcos Juarez'
own_license='SPDX-License-Identifier:'

has_adobe() {
    head -n 20 | grep -Eq "$adobe"
}

has_own() {
    header=$(head -n 20)
    printf '%s\n' "$header" | grep -Eq "$own_owner" && printf '%s\n' "$header" | grep -q "$own_license"
}

covered() {
    case "$1" in
        *.xml|*.html|*.less|*.css|*.graphqls) [ -f "${1%%/*}/registration.php" ] ;;
        *) return 1 ;;
    esac
}

failed=0
while IFS= read -r file; do
    covered "$file" || continue
    [ -f "$file" ] || continue

    if git cat-file -e "$base:$file" 2> /dev/null && ! grep -qxF "$file" "$added"; then
        if git show "$base:$file" | has_adobe; then
            if ! has_adobe < "$file"; then
                echo "$file: Adobe's copyright notice was removed or replaced; keep it (OSL-3.0 and AFL-3.0, section 6)"
                failed=1
            fi
            continue
        fi
    fi

    if ! has_adobe < "$file" && ! has_own < "$file"; then
        echo "$file: no Adobe copyright notice and no msi-core header (Copyright YYYY Jeanmarcos Juarez plus SPDX-License-Identifier)"
        failed=1
    fi
done < "$changed"

exit "$failed"
