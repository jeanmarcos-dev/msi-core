#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    BEFORE="$BATS_TEST_TMPDIR/before"
    AFTER="$BATS_TEST_TMPDIR/after"
    OUT="$BATS_TEST_TMPDIR/out"
    mkdir -p "$REPO/.github/scripts" "$BATS_TEST_TMPDIR/bin" "$BATS_TEST_TMPDIR/svc/bin"
    cp "$BATS_TEST_DIRNAME/../ci-semver.sh" "$REPO/.github/scripts/"
    for tree in "$BEFORE" "$AFTER"; do
        mkdir -p "$tree/InventoryApi/Api" "$tree/InventoryCatalog" "$tree/docs" "$tree/dev"
        touch "$tree/InventoryApi/registration.php" "$tree/InventoryCatalog/registration.php"
    done
    cat > "$BATS_TEST_TMPDIR/bin/php" <<'STUB'
#!/bin/sh
printf '%s\n' "$*" >> "$CALLS"
for arg in "$@"; do
    case "$arg" in
        --log-output-location=*) log=${arg#--log-output-location=} ;;
    esac
done
for arg in "$@"; do
    case "$arg" in
        */before) ls "$arg/app/code/Magento" > "$STAGED" ;;
    esac
done
if [ -n "${FAKE_LOG:-}" ]; then
    printf '%s\n' "$FAKE_LOG" > "$log"
fi
exit "${FAKE_EXIT:-0}"
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/php"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    export STAGED="$BATS_TEST_TMPDIR/staged"
    export FAKE_LOG='Suggested version increase (based on PHP code analysis and files changed): MINOR'
    cd "$REPO"
}

run_semver() {
    run "$TEST_SH" .github/scripts/ci-semver.sh "$BATS_TEST_TMPDIR/svc" "$BEFORE" "$AFTER" "$OUT"
}

@test "stages only the module directories under app/code/Magento and compares the staged trees" {
    run_semver

    [ "$status" -eq 0 ]
    [ "$(cat "$STAGED")" = "$(printf 'InventoryApi\nInventoryCatalog')" ]
    grep -qF -- "$BATS_TEST_TMPDIR/svc/bin/svc compare $OUT/stage/before $OUT/stage/after" "$CALLS"
}

@test "runs the tool without a memory limit and keeps the report in the output directory" {
    run_semver

    grep -qF -- '-d memory_limit=-1' "$CALLS"
    grep -qF -- "--log-output-location=$OUT/svc.log" "$CALLS"
    [ -f "$OUT/svc.log" ]
}

@test "writes the suggested level and the full report to the summary" {
    run_semver

    [ "$status" -eq 0 ]
    grep -qF 'Suggested level: **MINOR**' "$OUT/summary.md"
    grep -qF 'Suggested version increase (based on PHP code analysis and files changed): MINOR' "$OUT/summary.md"
}

@test "succeeds whatever level it reports" {
    FAKE_LOG='Suggested version increase (based on PHP code analysis and files changed): MAJOR' run_semver

    [ "$status" -eq 0 ]
    grep -qF 'Suggested level: **MAJOR**' "$OUT/summary.md"
}

@test "fails and says so in the summary when the tool itself fails" {
    FAKE_EXIT=255 FAKE_LOG= run_semver

    [ "$status" -ne 0 ]
    grep -qF 'The checker failed' "$OUT/summary.md"
}

@test "fails when the tool produces no report" {
    FAKE_LOG= run_semver

    [ "$status" -ne 0 ]
    grep -qF 'The checker failed' "$OUT/summary.md"
}

@test "does not pass an allowed level and says no level was declared when there is no semver label" {
    SEMVER_LABELS='ci bug' run_semver

    [ "$status" -eq 0 ]
    ! grep -qE "stage/after [1-3] " "$CALLS"
    grep -qF 'Declared level: none' "$OUT/summary.md"
}

@test "passes the declared level to the tool as the allowed change level" {
    SEMVER_LABELS='semver:patch' run_semver
    grep -qF "stage/after 1 --log-output-location" "$CALLS"

    SEMVER_LABELS='semver:minor' run_semver
    grep -qF "stage/after 2 --log-output-location" "$CALLS"

    SEMVER_LABELS='semver:major' run_semver
    grep -qF "stage/after 3 --log-output-location" "$CALLS"
}

@test "ignores labels that are not semver labels" {
    SEMVER_LABELS='ci semver:minor bug' run_semver

    [ "$status" -eq 0 ]
    grep -qF 'Declared level: **MINOR**' "$OUT/summary.md"
}

@test "succeeds when the reported level is within the declared level" {
    SEMVER_LABELS='semver:minor' run_semver

    [ "$status" -eq 0 ]
    grep -qF 'Suggested level: **MINOR**' "$OUT/summary.md"
    grep -qF 'Declared level: **MINOR**' "$OUT/summary.md"
}

@test "fails and keeps the report when the reported level is above the declared level" {
    SEMVER_LABELS='semver:patch' FAKE_EXIT=1 run_semver

    [ "$status" -ne 0 ]
    grep -qF 'Suggested level: **MINOR**' "$OUT/summary.md"
    grep -qF 'above the declared level **PATCH**' "$OUT/summary.md"
    grep -qF 'Suggested version increase' "$OUT/summary.md"
}

@test "treats an exit status without a report as a tool failure even with a label" {
    SEMVER_LABELS='semver:patch' FAKE_EXIT=1 FAKE_LOG= run_semver

    [ "$status" -ne 0 ]
    grep -qF 'The checker failed' "$OUT/summary.md"
}

@test "fails without running the tool when more than one semver label is set" {
    SEMVER_LABELS='semver:patch semver:major' run_semver

    [ "$status" -ne 0 ]
    [ ! -f "$CALLS" ]
    grep -qF 'More than one semver label' "$OUT/summary.md"
    grep -qF 'semver:patch semver:major' "$OUT/summary.md"
}

@test "does not ask the tool for a changed files log" {
    run_semver

    ! grep -qF 'filechange-output-location' "$CALLS"
}
