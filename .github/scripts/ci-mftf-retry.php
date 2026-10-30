<?php
declare(strict_types=1);

$usage = "usage: ci-mftf-retry.php failed <allure-results> | flaky <first-results> <retry-results> <shard>\n";
$mode = $argv[1] ?? '';
$results = static function (string $directory): array {
    $tests = [];
    foreach (glob(rtrim($directory, '/') . '/*-result.json') as $file) {
        $result = json_decode((string) file_get_contents($file), true);
        if (!isset($result['fullName']) || !preg_match('/\\\\_([^\\\\]+)\\\\[^\\\\]+\\\\(\w+)Cest::/', $result['fullName'], $match)) {
            continue;
        }
        $key = $match[1] . ':' . $match[2];
        if (!isset($tests[$key]) || $tests[$key]['start'] <= $result['start']) {
            $tests[$key] = [
                'test' => $match[2],
                'suite' => $match[1],
                'status' => $result['status'],
                'start' => $result['start'],
                'message' => trim(strtok($result['statusDetails']['message'] ?? '', "\n") ?: ''),
            ];
        }
    }
    ksort($tests);
    return $tests;
};
$notPassed = static fn (array $test): bool => in_array($test['status'], ['failed', 'broken'], true);

if ($mode === 'failed' && isset($argv[2])) {
    foreach (array_filter($results($argv[2]), $notPassed) as $test) {
        echo ($test['suite'] === 'default' ? '' : $test['suite'] . ':') . $test['test'] . "\n";
    }
    exit(0);
}

if ($mode === 'flaky' && isset($argv[2], $argv[3], $argv[4])) {
    $retried = $results($argv[3]);
    $flaky = array_filter(
        $results($argv[2]),
        static fn (array $test, string $key): bool => $notPassed($test) && ($retried[$key]['status'] ?? '') === 'passed',
        ARRAY_FILTER_USE_BOTH
    );
    if ($flaky === []) {
        exit(0);
    }
    printf("\n### Flaky tests, shard %s\n\nThese failed on the first run and passed on the retry.\n\n", $argv[4]);
    echo "| Test | Suite | First error |\n|---|---|---|\n";
    foreach ($flaky as $test) {
        printf("| %s | %s | %s |\n", $test['test'], $test['suite'], str_replace('|', '\\|', $test['message']));
    }
    exit(0);
}

fwrite(STDERR, $usage);
exit(2);
