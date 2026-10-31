#!/usr/bin/env bats

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    git init -q "$REPO"
    git -C "$REPO" -c user.name=t -c user.email=t@t commit -q --allow-empty -m one
    git -C "$REPO" tag 1.0.9
    git -C "$REPO" -c user.name=t -c user.email=t@t commit -q --allow-empty -m two
    git -C "$REPO" tag 1.1.3
    git -C "$REPO" tag nightly-1
    git -C "$REPO" -c user.name=t -c user.email=t@t commit -q --allow-empty -m three
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-release.sh"
}

@test "prints the newest release tag reachable from HEAD" {
    run sh "$SCRIPT" "$REPO"

    [ "$status" -eq 0 ]
    [ "$output" = "1.1.3" ]
}

@test "ignores tags that are not plain versions" {
    git -C "$REPO" tag v9-rc

    run sh "$SCRIPT" "$REPO"

    [ "$output" = "1.1.3" ]
}

@test "fails when there is no release tag" {
    git init -q "$BATS_TEST_TMPDIR/empty"
    git -C "$BATS_TEST_TMPDIR/empty" -c user.name=t -c user.email=t@t commit -q --allow-empty -m one

    run sh "$SCRIPT" "$BATS_TEST_TMPDIR/empty"

    [ "$status" -ne 0 ]
}
