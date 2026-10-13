#!/bin/sh
set -eu

cd "$(git rev-parse --show-toplevel)"

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

for reg in */registration.php; do
    [ -f "$reg" ] && dirname "$reg"
done | sort > "$WORK/modules"

while read -r dir; do
    printf 'files\t%s/registration.php\n' "$dir"
    printf 'psr-4\tMagento\\\\%s\\\\\t%s/\n' "$dir" "$dir"
done < "$WORK/modules" | sort > "$WORK/expected"

php -r '
    $autoload = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR)["autoload"] ?? [];
    foreach ($autoload["files"] ?? [] as $file) {
        echo "files\t", $file, "\n";
    }
    foreach ($autoload["psr-4"] ?? [] as $namespace => $path) {
        echo "psr-4\t", str_replace("\\", "\\\\", $namespace), "\t", $path, "\n";
    }
' > "$WORK/actual.unsorted"
sort "$WORK/actual.unsorted" > "$WORK/actual"

comm -23 "$WORK/expected" "$WORK/actual" > "$WORK/missing"
comm -13 "$WORK/expected" "$WORK/actual" > "$WORK/extra"

status=0
if [ -s "$WORK/missing" ]; then
    echo "composer.json is missing autoload entries for modules that have registration.php:"
    sed 's/^/  /' "$WORK/missing" | tr '\t' ' '
    status=1
fi
if [ -s "$WORK/extra" ]; then
    echo "composer.json has autoload entries that do not match a module with registration.php:"
    sed 's/^/  /' "$WORK/extra" | tr '\t' ' '
    status=1
fi
[ "$status" -eq 0 ] && echo "root autoload: ok ($(wc -l < "$WORK/modules" | tr -d ' ') modules)"
exit "$status"
