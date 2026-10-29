#!/usr/bin/env bats

setup() {
    OUTPUT="$BATS_TEST_TMPDIR/github-output"
    SMOKE="$BATS_TEST_TMPDIR/smoke.txt"
    printf 'AdminOneTest\nStorefrontTwoTest\n\nAdminThreeTest\n' > "$SMOKE"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-plan.sh"
    export GITHUB_OUTPUT="$OUTPUT" MFTF_SMOKE_FILE="$SMOKE" MFTF_ALL_SHARDS=20 MFTF_GROUP_SHARDS=4
}

output_of() {
    grep "^$1=" "$OUTPUT" | cut -d= -f2-
}

@test "runs the smoke list in one job on a pull request" {
    EVENT_NAME=pull_request run sh "$SCRIPT"

    [ "$status" -eq 0 ]
    [ "$(output_of shards)" = "1" ]
    [ "$(output_of matrix)" = '{"shard":[1]}' ]
    [ "$(output_of tests)" = "AdminOneTest,StorefrontTwoTest,AdminThreeTest" ]
    [ -z "$(output_of group)" ]
}

@test "splits the whole suite over the default number of jobs" {
    EVENT_NAME=workflow_dispatch run sh "$SCRIPT"

    [ "$status" -eq 0 ]
    [ "$(output_of shards)" = "20" ]
    [ "$(output_of matrix)" = "{\"shard\":[$(seq -s, 1 20)]}" ]
    [ -z "$(output_of tests)" ]
}

@test "uses fewer jobs for a group" {
    EVENT_NAME=workflow_dispatch INPUT_GROUP=store_pickup run sh "$SCRIPT"

    [ "$status" -eq 0 ]
    [ "$(output_of shards)" = "4" ]
    [ "$(output_of group)" = "store_pickup" ]
}

@test "runs a list of tests in one job" {
    EVENT_NAME=workflow_dispatch INPUT_TESTS="AdminOneTest, AdminTwoTest" run sh "$SCRIPT"

    [ "$status" -eq 0 ]
    [ "$(output_of shards)" = "1" ]
    [ "$(output_of tests)" = "AdminOneTest,AdminTwoTest" ]
}

@test "honours the number of jobs asked for" {
    EVENT_NAME=workflow_dispatch INPUT_SHARDS=7 run sh "$SCRIPT"

    [ "$status" -eq 0 ]
    [ "$(output_of shards)" = "7" ]
}

@test "never starts more jobs than there are tests in the list" {
    EVENT_NAME=workflow_dispatch INPUT_TESTS="AdminOneTest,AdminTwoTest" INPUT_SHARDS=5 run sh "$SCRIPT"

    [ "$status" -eq 0 ]
    [ "$(output_of shards)" = "2" ]
}

@test "caps the jobs at the 20 GitHub runs at once" {
    EVENT_NAME=workflow_dispatch INPUT_SHARDS=30 run sh "$SCRIPT"

    [ "$status" -eq 1 ]
    [[ "$output" == *"between 1 and 20"* ]]
}

@test "rejects a job count that is not a number" {
    EVENT_NAME=workflow_dispatch INPUT_SHARDS=many run sh "$SCRIPT"

    [ "$status" -eq 1 ]
}

@test "does not accept a group and a list of tests together" {
    EVENT_NAME=workflow_dispatch INPUT_GROUP=msi INPUT_TESTS=AdminOneTest run sh "$SCRIPT"

    [ "$status" -eq 1 ]
    [[ "$output" == *"either a group or a list of tests"* ]]
}
