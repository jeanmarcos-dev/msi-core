#!/usr/bin/env bats

setup() {
    ROOT="$BATS_TEST_DIRNAME/../../.."
    WORKFLOW="$ROOT/.github/workflows/integration.yml"
    DECLARED="$BATS_TEST_TMPDIR/declared"
    EXISTING="$BATS_TEST_TMPDIR/existing"
    awk '
        /^ +modules: >-$/ { collecting = 1; next }
        collecting && /^ +[A-Za-z]+$/ { print $1; next }
        { collecting = 0 }
    ' "$WORKFLOW" | sort > "$DECLARED"
    (cd "$ROOT" && for dir in */Test/Integration; do echo "${dir%%/*}"; done) | sort > "$EXISTING"
}

@test "assigns every module that has integration tests to a job" {
    [ -s "$EXISTING" ]

    run comm -13 "$DECLARED" "$EXISTING"

    [ "$status" -eq 0 ]
    [ -z "$output" ]
}

@test "does not list a module that has no integration tests" {
    run comm -23 "$DECLARED" "$EXISTING"

    [ "$status" -eq 0 ]
    [ -z "$output" ]
}

@test "assigns no module to more than one job" {
    run uniq -d "$DECLARED"

    [ "$status" -eq 0 ]
    [ -z "$output" ]
}

@test "is not part of the tests gate" {
    ! grep -q "integration" "$ROOT/.github/workflows/tests.yml"
}

@test "runs nightly, on demand, and on pull requests that touch the integration suite" {
    grep -qE "^  schedule:" "$WORKFLOW"
    grep -qE "^  workflow_dispatch:" "$WORKFLOW"
    grep -qE "^  pull_request:" "$WORKFLOW"
    grep -qF "      - '**/Test/Integration/**'" "$WORKFLOW"
    grep -qF "      - '**/Test/_files/**'" "$WORKFLOW"
    grep -qF "      - '.github/workflows/integration.yml'" "$WORKFLOW"
    grep -qF "      - '.github/scripts/ci-integration*'" "$WORKFLOW"
    ! grep -qE "^  push:" "$WORKFLOW"
}

@test "gives the runner a sendmail before the tests that send mail" {
    grep -qF 'sudo tee /usr/sbin/sendmail' "$WORKFLOW"
    [ "$(grep -n 'sudo tee /usr/sbin/sendmail' "$WORKFLOW" | cut -d: -f1)" -lt "$(grep -n 'ci-integration-modules.sh' "$WORKFLOW" | cut -d: -f1)" ]
}
