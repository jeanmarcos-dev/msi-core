#!/bin/sh
set -eu

usage='usage: ci-suites-schema.sh <database-a> <database-b> <allowlist-file>'
first=${1:?$usage}
second=${2:?$usage}
allowlist=${3:?$usage}

normalize() {
    mysqldump --host="${DB_HOST:-127.0.0.1}" --user="${DB_USER:-root}" --password="${DB_PASSWORD:-root}" \
        --no-data --skip-comments --skip-add-drop-table --skip-set-charset --routines --triggers "$1" \
        | sed -E 's/ AUTO_INCREMENT=[0-9]+//; /^\/\*!(40101|50503) SET /d' \
        | awk -v allowlist="$allowlist" '
            BEGIN {
                while ((getline pattern < allowlist) > 0) {
                    if (pattern != "") {
                        patterns[++count] = "^" pattern "$"
                    }
                }
            }
            /^CREATE TABLE `/ {
                name = $3
                gsub(/`/, "", name)
                skipping = 0
                for (i = 1; i <= count; i++) {
                    if (name ~ patterns[i]) {
                        skipping = 1
                    }
                }
            }
            skipping {
                if ($0 ~ /^\) ENGINE/) {
                    skipping = 0
                    dropBlank = 1
                }
                next
            }
            dropBlank {
                dropBlank = 0
                if ($0 == "") {
                    next
                }
            }
            { print }
        '
}

dir=$(mktemp -d)
trap 'rm -rf "$dir"' EXIT
normalize "$first" > "$dir/first.sql"
normalize "$second" > "$dir/second.sql"

if diff -u "$dir/first.sql" "$dir/second.sql"; then
    echo "schemas are identical: $first and $second"
else
    echo "the schemas of $first and $second differ" >&2
    exit 1
fi
