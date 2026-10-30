#!/usr/bin/env bats

setup() {
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static-baseline.php"
    MAGENTO=/runner/magento
    JUNIT="$BATS_TEST_TMPDIR/junit.xml"
    BASELINE="$BATS_TEST_TMPDIR/baseline.txt"
}

junit() {
    printf '<?xml version="1.0"?>\n<testsuites><testsuite name="s">%s</testsuite></testsuites>\n' "$1" > "$JUNIT"
}

@test "passes when nothing failed and PHPUnit succeeded" {
    junit '<testcase class="A\BTest" name="testOk"/>'
    : > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 0

    [ "$status" -eq 0 ]
}

@test "fails on a failing test that the baseline does not list" {
    junit '<testcase class="A\BTest" name="testBad"><failure type="E">A\BTest::testBad
Something is wrong
#0 trace</failure></testcase>'
    : > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 1 ]
    [[ "$output" == *"new failure: A\\BTest::testBad |  | Something is wrong"* ]]
}

@test "passes when the baseline lists the failing test" {
    junit '<testcase class="A\BTest" name="testBad"><failure type="E">A\BTest::testBad
Something is wrong
#0 trace</failure></testcase>'
    echo 'A\BTest::testBad |  | Something is wrong' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 0 ]
}

@test "counts an error like a failure" {
    junit '<testcase class="A\BTest" name="testBad"><error type="E">A\BTest::testBad
Boom
#0 trace</error></testcase>'
    : > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 1 ]
    [[ "$output" == *"new failure: A\\BTest::testBad |  | Boom"* ]]
}

@test "tells apart the data sets of one failing test" {
    junit '<testcase class="A\BTest" name="testSets"><failure type="E">A\BTest::testSets
Passed: 1, Failed: 2, Incomplete: 0, Skipped: 0.
Data set: /runner/magento/app/code/Magento/One
Version must not be specified
#0 trace
Data set: /runner/magento/app/code/Magento/Two
Version must not be specified
#0 trace</failure></testcase>'
    echo 'A\BTest::testSets | app/code/Magento/One | Version must not be specified' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 1

    [ "$status" -eq 1 ]
    [[ "$output" == *"new failure: A\\BTest::testSets | app/code/Magento/Two | Version must not be specified"* ]]
    [[ "$output" != *"new failure: A\\BTest::testSets | app/code/Magento/One"* ]]
}

@test "reads a data set that spans several lines" {
    junit '<testcase class="A\BTest" name="testArray"><failure type="E">A\BTest::testArray
Passed: 0, Failed: 1, Incomplete: 0, Skipped: 0.
Data set: array (
  0 => &apos;X&apos;,
  1 => &apos;Y&apos;,
)
Invalid type
#0 trace</failure></testcase>'
    echo "A\\BTest::testArray | array ( 0 => 'X', 1 => 'Y', ) | Invalid type" > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 1

    [ "$status" -eq 0 ]
}

@test "accepts wildcards in a baseline entry" {
    junit '<testcase class="A\BTest" name="testSets"><failure type="E">A\BTest::testSets
Passed: 0, Failed: 2, Incomplete: 0, Skipped: 0.
Data set: /runner/magento/app/code/Magento/One
Missing in root &apos;composer.json&apos;: a
#0 trace
Data set: /runner/magento/app/code/Magento/Two
Missing in root &apos;composer.json&apos;: b
#0 trace</failure></testcase>'
    echo "A\\BTest::testSets | * | Missing in root 'composer.json'*" > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 1

    [ "$status" -eq 0 ]
}

@test "ignores blank lines in the baseline" {
    junit '<testcase class="A\BTest" name="testBad"><failure type="E">A\BTest::testBad
Oops
#0 trace</failure></testcase>'
    printf '\nA\\BTest::testBad |  | Oops\n\n' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 0 ]
}

@test "warns about a baseline entry that no failure matches, without failing" {
    junit '<testcase class="A\BTest" name="testOk"/>'
    echo 'A\BTest::testGone |  | Old failure' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 0

    [ "$status" -eq 0 ]
    [[ "$output" == *"stale baseline entry: A\\BTest::testGone |  | Old failure"* ]]
}

@test "fails when PHPUnit failed without reporting a failing test" {
    junit '<testcase class="A\BTest" name="testOk"/>'
    : > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 255

    [ "$status" -eq 1 ]
    [[ "$output" == *"PHPUnit exited with 255"* ]]
}

@test "fails when there is no report" {
    : > "$BASELINE"

    run php "$SCRIPT" "$BATS_TEST_TMPDIR/missing.xml" "$BASELINE" "$MAGENTO" 0

    [ "$status" -eq 1 ]
}

@test "lists the failures as baseline entries on request" {
    junit '<testcase class="A\BTest" name="testBad"><failure type="E">A\BTest::testBad
Oops
#0 trace</failure></testcase>'

    run php "$SCRIPT" --list "$JUNIT" "$MAGENTO"

    [ "$status" -eq 0 ]
    [ "$output" = 'A\BTest::testBad |  | Oops' ]
}

redundant_failure() {
    junit '<testcase class="A\DependencyTest" name="testRedundant"><failure type="E">A\DependencyTest::testRedundant
Some dependencies required by composer.json are not used in the module and must be removed:

Module Magento\One: hard [Magento\Config]
Module Magento\Two: hard [Magento\Sales, Magento\Quote]

/runner/magento/dev/tests/static/DependencyTest.php:879
#0 trace</failure></testcase>'
}

@test "lists a redundant dependency failure as one entry per module" {
    redundant_failure

    run php "$SCRIPT" --list "$JUNIT" "$MAGENTO"

    [ "$status" -eq 0 ]
    [ "${lines[0]}" = 'A\DependencyTest::testRedundant |  | Module Magento\One: hard (Magento\Config)' ]
    [ "${lines[1]}" = 'A\DependencyTest::testRedundant |  | Module Magento\Two: hard (Magento\Sales, Magento\Quote)' ]
    [ "${#lines[@]}" -eq 2 ]
}

@test "passes when the baseline lists every redundant module exactly" {
    redundant_failure
    printf '%s\n' \
        'A\DependencyTest::testRedundant |  | Module Magento\One: hard (Magento\Config)' \
        'A\DependencyTest::testRedundant |  | Module Magento\Two: hard (Magento\Sales, Magento\Quote)' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 0 ]
}

@test "fails on a redundant dependency in a module the baseline does not list" {
    redundant_failure
    echo 'A\DependencyTest::testRedundant |  | Module Magento\One: hard (Magento\Config)' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 1 ]
    [[ "$output" == *"new failure: A\\DependencyTest::testRedundant |  | Module Magento\\Two: hard (Magento\\Sales, Magento\\Quote)"* ]]
}

@test "fails on a new redundant package in a module the baseline lists" {
    redundant_failure
    printf '%s\n' \
        'A\DependencyTest::testRedundant |  | Module Magento\One: hard (Magento\Config)' \
        'A\DependencyTest::testRedundant |  | Module Magento\Two: hard (Magento\Sales)' > "$BASELINE"

    run php "$SCRIPT" "$JUNIT" "$BASELINE" "$MAGENTO" 2

    [ "$status" -eq 1 ]
    [[ "$output" == *"new failure: A\\DependencyTest::testRedundant |  | Module Magento\\Two: hard (Magento\\Sales, Magento\\Quote)"* ]]
}
