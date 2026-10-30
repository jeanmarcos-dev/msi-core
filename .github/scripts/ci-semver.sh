#!/bin/sh
set -eu

svc=${1:?usage: ci-semver.sh <svc-dir> <before-dir> <after-dir> <out-dir>}
before=${2:?usage: ci-semver.sh <svc-dir> <before-dir> <after-dir> <out-dir>}
after=${3:?usage: ci-semver.sh <svc-dir> <before-dir> <after-dir> <out-dir>}
out=${4:?usage: ci-semver.sh <svc-dir> <before-dir> <after-dir> <out-dir>}

mkdir -p "$out"
out=$(cd "$out" && pwd)
before=$(cd "$before" && pwd)
after=$(cd "$after" && pwd)
svc=$(cd "$svc" && pwd)
summary="$out/summary.md"

declared=""
for label in ${SEMVER_LABELS:-}; do
    case "$label" in
        semver:patch | semver:minor | semver:major) declared="$declared $label" ;;
    esac
done
declared=${declared# }

case "$declared" in
    *" "*)
        {
            printf '### Semantic version check\n\n'
            printf 'More than one semver label is set: %s. Keep only one.\n' "$declared"
        } > "$summary"
        exit 1
        ;;
esac

allowed=""
declared_level="none"
case "$declared" in
    semver:patch) allowed=1 declared_level="**PATCH**" ;;
    semver:minor) allowed=2 declared_level="**MINOR**" ;;
    semver:major) allowed=3 declared_level="**MAJOR**" ;;
esac

stage() {
    mkdir -p "$2/app/code/Magento"
    for registration in "$1"/*/registration.php; do
        cp -R "$(dirname "$registration")" "$2/app/code/Magento/"
    done
}

rm -rf "$out/stage"
stage "$before" "$out/stage/before"
stage "$after" "$out/stage/after"

rm -f "$out/svc.log"
status=0
php -d memory_limit=-1 "$svc/bin/svc" compare "$out/stage/before" "$out/stage/after" $allowed \
    --log-output-location="$out/svc.log" || status=$?

exceeded=0
if [ "$status" -eq 1 ] && [ -n "$allowed" ] && [ -s "$out/svc.log" ]; then
    exceeded=1
fi

if { [ "$status" -ne 0 ] && [ "$exceeded" -eq 0 ]; } || [ ! -s "$out/svc.log" ]; then
    {
        printf '### Semantic version check\n\n'
        printf 'The checker failed (exit status %s) and produced no report.\n' "$status"
    } > "$summary"
    exit 1
fi

level=$(sed -n 's/^Suggested version increase.*: //p' "$out/svc.log" | head -n 1)
{
    printf '### Semantic version check\n\n'
    printf 'Suggested level: **%s**\n\n' "${level:-UNKNOWN}"
    printf 'Declared level: %s\n\n' "$declared_level"
    if [ "$exceeded" -eq 1 ]; then
        printf 'The suggested level is above the declared level %s.\n\n' "$declared_level"
    fi
    printf '<details><summary>Full report</summary>\n\n```\n'
    cat "$out/svc.log"
    printf '```\n\n</details>\n'
} > "$summary"
exit "$exceeded"
