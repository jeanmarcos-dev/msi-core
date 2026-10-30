#!/usr/bin/env bats

setup() {
    ROOT="$BATS_TEST_DIRNAME/../../.."
    WORKFLOW="$ROOT/.github/workflows/mftf.yml"
    SMOKE="$ROOT/.github/scripts/mftf-smoke-tests.txt"
}

@test "is not part of the tests gate" {
    ! grep -q "mftf" "$ROOT/.github/workflows/tests.yml"
}

@test "runs on demand and can be called by a release workflow" {
    grep -qE "^  workflow_dispatch:" "$WORKFLOW"
    grep -qE "^  workflow_call:" "$WORKFLOW"
}

@test "never runs on a schedule or on a push" {
    ! grep -qE "^  schedule:" "$WORKFLOW"
    ! grep -qE "^  push:" "$WORKFLOW"
}

@test "runs on a pull request only when the pull request touches its own files" {
    grep -qE "^  pull_request:" "$WORKFLOW"
    grep -qE "^    paths:" "$WORKFLOW"
    ! grep -qE "^    paths-ignore:" "$WORKFLOW"
    awk '/^    paths:/ { collecting = 1; next } collecting && /^      - / { print $2; next } { collecting = 0 }' "$WORKFLOW" | tr -d "'" > "$BATS_TEST_TMPDIR/paths"
    [ -s "$BATS_TEST_TMPDIR/paths" ]
    ! grep -vE '^\.github/(workflows/mftf\.yml|scripts/(ci-mftf\*|mftf-smoke-tests\.txt|tests/ci-mftf\*))$' "$BATS_TEST_TMPDIR/paths"
}

@test "cancels a superseded run and reads the repository only" {
    grep -qF "cancel-in-progress: true" "$WORKFLOW"
    grep -qE "^  contents: read$" "$WORKFLOW"
}

@test "takes the same inputs when dispatched and when called" {
    for input in group tests shards; do
        [ "$(grep -cE "^      $input:$" "$WORKFLOW")" -eq 2 ]
    done
}

@test "keeps the whole suite inside the 20 jobs GitHub runs at once" {
    shards=$(grep -oE "MFTF_ALL_SHARDS:-[0-9]+" "$ROOT/.github/scripts/ci-mftf-plan.sh" | grep -oE "[0-9]+$")

    [ "$shards" -ge 1 ]
    [ "$shards" -le 20 ]
}

@test "names only msi-core tests in the smoke list, each once, none skipped" {
    [ -s "$SMOKE" ]
    [ -z "$(sort "$SMOKE" | uniq -d)" ]
    while read -r name; do
        grep -qE "<test name=\"$name\"" "$ROOT"/*/Test/Mftf/Test/*.xml
    done < "$SMOKE"
}

@test "keeps the smoke list to about ten tests" {
    [ "$(wc -l < "$SMOKE")" -ge 8 ]
    [ "$(wc -l < "$SMOKE")" -le 12 ]
}

@test "runs the browser jobs on an image whose libxml2 still accepts the MFTF schemas" {
    awk '/^  mftf:/ { job = 1 } job && /runs-on:/ { print $2; exit }' "$WORKFLOW" > "$BATS_TEST_TMPDIR/runner"

    [ "$(cat "$BATS_TEST_TMPDIR/runner")" = "ubuntu-24.04" ]
}

@test "warms the static files after the instance is configured and before the tests run" {
    warm=$(grep -n "ci-mftf-warmup.sh" "$WORKFLOW" | head -1 | cut -d: -f1)
    configure=$(grep -n "ci-mftf-configure.sh" "$WORKFLOW" | head -1 | cut -d: -f1)
    tests=$(grep -n "ci-mftf.sh" "$WORKFLOW" | head -1 | cut -d: -f1)
    [ -n "$warm" ]
    [ "$warm" -gt "$configure" ]
    [ "$warm" -lt "$tests" ]
}
