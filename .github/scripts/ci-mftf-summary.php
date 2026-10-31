<?php
declare(strict_types=1);

$directory = $argv[1] ?? null;
if ($directory === null) {
    fwrite(STDERR, "usage: ci-mftf-summary.php <allure-results directory>\n");
    exit(2);
}

$order = ['failed' => 0, 'broken' => 1, 'skipped' => 2, 'passed' => 3];
$results = [];
foreach (glob(rtrim($directory, '/') . '/*-result.json') as $file) {
    $result = json_decode((string) file_get_contents($file), true);
    if (!isset($result['fullName']) || !preg_match('/\\\\_([^\\\\]+)\\\\[^\\\\]+\\\\(\w+)Cest::/', $result['fullName'], $match)) {
        continue;
    }
    $key = $match[1] . ':' . $match[2];
    if (isset($results[$key]) && $results[$key]['start'] > $result['start']) {
        continue;
    }
    $results[$key] = [
        'start' => $result['start'],
        'test' => $match[2],
        'suite' => $match[1],
        'status' => $result['status'],
        'seconds' => ($result['stop'] - $result['start']) / 1000,
        'message' => trim(strtok($result['statusDetails']['message'] ?? '', "\n") ?: ''),
    ];
}

if ($results === []) {
    echo "No MFTF result found in $directory\n";
    exit(0);
}

$results = array_values($results);

usort($results, static fn (array $a, array $b): int => [$order[$a['status']] ?? 4, $a['test']] <=> [$order[$b['status']] ?? 4, $b['test']]);

$count = static fn (string $status): int => count(array_filter($results, static fn (array $r): bool => $r['status'] === $status));
$total = array_sum(array_column($results, 'seconds'));
printf(
    "%d tests: %d passed, %d failed, %d broken, %d skipped\n\n",
    count($results),
    $count('passed'),
    $count('failed'),
    $count('broken'),
    $count('skipped')
);
printf("Total %.1f s, mean %.1f s per test\n\n", $total, $total / count($results));
echo "| Test | Suite | Status | Seconds | Reason |\n|---|---|---|---|---|\n";
foreach ($results as $r) {
    printf("| %s | %s | %s | %.1f | %s |\n", $r['test'], $r['suite'], $r['status'], $r['seconds'], str_replace('|', '\\|', $r['message']));
}
