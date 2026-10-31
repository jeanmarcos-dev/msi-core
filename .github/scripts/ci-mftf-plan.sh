#!/bin/sh
set -eu

all_shards=${MFTF_ALL_SHARDS:-20}
group_shards=${MFTF_GROUP_SHARDS:-4}
smoke_file=${MFTF_SMOKE_FILE:-$(dirname "$0")/mftf-smoke-tests.txt}
group=${INPUT_GROUP:-}
shards=${INPUT_SHARDS:-}
tests=$(printf '%s' "${INPUT_TESTS:-}" | tr -d ' \n' )

if [ "${EVENT_NAME:-}" = pull_request ]; then
    group=
    shards=1
    tests=$(grep -v '^$' "$smoke_file" | paste -sd, -)
fi

if [ -n "$group" ] && [ -n "$tests" ]; then
    echo "give either a group or a list of tests, not both" >&2
    exit 1
fi

if [ -z "$shards" ]; then
    if [ -n "$tests" ]; then
        shards=1
    elif [ -n "$group" ]; then
        shards=$group_shards
    else
        shards=$all_shards
    fi
fi

case "$shards" in
    '' | *[!0-9]*)
        echo "shards must be a number between 1 and 20" >&2
        exit 1
        ;;
esac
if [ "$shards" -lt 1 ] || [ "$shards" -gt 20 ]; then
    echo "shards must be between 1 and 20" >&2
    exit 1
fi

if [ -n "$tests" ]; then
    count=$(printf '%s' "$tests" | tr ',' '\n' | grep -c .)
    if [ "$shards" -gt "$count" ]; then
        shards=$count
    fi
fi

{
    echo "shards=$shards"
    echo "matrix={\"shard\":[$(seq -s, 1 "$shards")]}"
    echo "tests=$tests"
    echo "group=$group"
} >> "$GITHUB_OUTPUT"
