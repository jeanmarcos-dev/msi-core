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
