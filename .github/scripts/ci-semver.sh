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
php -d memory_limit=-1 "$svc/bin/svc" compare "$out/stage/before" "$out/stage/after" \
    --log-output-location="$out/svc.log" \
    --filechange-output-location="$out/changed-files.log" || status=$?

summary="$out/summary.md"
if [ "$status" -eq 0 ] && [ -s "$out/svc.log" ]; then
    level=$(sed -n 's/^Suggested version increase.*: //p' "$out/svc.log" | head -n 1)
    {
        printf '### Semantic version check\n\n'
        printf 'Suggested level: **%s**\n\n' "${level:-UNKNOWN}"
        printf '<details><summary>Full report</summary>\n\n```\n'
        cat "$out/svc.log"
        printf '```\n\n</details>\n'
    } > "$summary"
else
    {
        printf '### Semantic version check\n\n'
        printf 'The checker failed (exit status %s) and produced no report.\n' "$status"
    } > "$summary"
    exit 1
fi
