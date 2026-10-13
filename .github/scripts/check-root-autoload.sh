#!/bin/sh
set -eu

cd "$(git rev-parse --show-toplevel)"

MODULES=$(for reg in */registration.php; do [ -f "$reg" ] && dirname "$reg"; done | sort)

EXPECTED=$(
    for dir in $MODULES; do
        printf 'files\t%s/registration.php\n' "$dir"
        printf 'psr-4\tMagento\\\\%s\\\\\t%s/\n' "$dir" "$dir"
    done | sort
)

ACTUAL=$(
    php -r '
        $autoload = json_decode(file_get_contents("composer.json"), true)["autoload"] ?? [];
        foreach ($autoload["files"] ?? [] as $file) {
            echo "files\t", $file, "\n";
        }
        foreach ($autoload["psr-4"] ?? [] as $namespace => $path) {
            echo "psr-4\t", str_replace("\\", "\\\\", $namespace), "\t", $path, "\n";
        }
    ' | sort
)

MISSING=$(printf '%s\n' "$EXPECTED" | grep -vxF -f /dev/fd/3 3<<EOF || true
$ACTUAL
EOF
)
EXTRA=$(printf '%s\n' "$ACTUAL" | grep -vxF -f /dev/fd/3 3<<EOF || true
$EXPECTED
EOF
)

status=0
if [ -n "$(printf '%s' "$MISSING" | tr -d '[:space:]')" ]; then
    echo "composer.json is missing autoload entries for modules that have registration.php:"
    printf '%s\n' "$MISSING" | sed 's/^/  /' | tr '\t' ' '
    status=1
fi
if [ -n "$(printf '%s' "$EXTRA" | tr -d '[:space:]')" ]; then
    echo "composer.json has autoload entries that do not match a module with registration.php:"
    printf '%s\n' "$EXTRA" | sed 's/^/  /' | tr '\t' ' '
    status=1
fi
[ "$status" -eq 0 ] && echo "root autoload: ok ($(printf '%s\n' "$MODULES" | grep -c .) modules)"
exit "$status"
