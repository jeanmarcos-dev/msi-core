<?php
declare(strict_types=1);

function normalize(string $text, string $magento): string
{
    $text = str_replace(rtrim($magento, '/') . '/', '', $text);

    return trim((string)preg_replace('/\s+/', ' ', $text));
}

function messageOf(array $lines, int $from): string
{
    for ($index = $from; $index < count($lines); $index++) {
        $line = trim($lines[$index]);
        if ($line !== '' && $line[0] !== '#') {
            return $line;
        }
    }

    return '';
}

function failureKeys(string $test, string $text, string $magento): array
{
    $lines = explode("\n", $text);
    array_shift($lines);
    $keys = [];
    $inSet = false;
    foreach ($lines as $index => $line) {
        if (strncmp($line, 'Data set: ', 10) !== 0) {
            continue;
        }
        $inSet = true;
        $set = substr($line, 10);
        $next = $index + 1;
        if (substr(rtrim($set), -1) === '(') {
            while ($next < count($lines) && rtrim($lines[$next]) !== ')') {
                $set .= "\n" . $lines[$next++];
            }
            $set .= "\n)";
            $next++;
        }
        $keys[] = $test . ' | ' . normalize($set, $magento) . ' | ' . normalize(messageOf($lines, $next), $magento);
    }
    if (!$inSet) {
        $keys[] = $test . ' |  | ' . normalize(messageOf($lines, 0), $magento);
    }

    return $keys;
}

function failures(string $junit, string $magento): array
{
    $previous = libxml_use_internal_errors(true);
    $report = simplexml_load_file($junit);
    libxml_use_internal_errors($previous);
    if ($report === false) {
        throw new RuntimeException("cannot read $junit");
    }
    $keys = [];
    foreach ($report->xpath('//testcase[failure or error]') as $case) {
        $node = isset($case->failure) ? $case->failure : $case->error;
        $test = ($case['class'] ?? $case['classname']) . '::' . $case['name'];
        $keys = array_merge($keys, failureKeys((string)$test, (string)$node, $magento));
    }

    return array_values(array_unique($keys));
}

if (($argv[1] ?? '') === '--list') {
    [, , $junit, $magento] = $argv + [null, null, null, ''];
    echo implode("\n", failures($junit, $magento)), "\n";
    exit(0);
}

[, $junit, $baselineFile, $magento, $status] = $argv + [null, null, null, '', '0'];
if ($baselineFile === null) {
    fwrite(STDERR, "usage: ci-static-baseline.php <junit> <baseline> <magento-dir> <phpunit-status>\n");
    exit(2);
}

try {
    $found = failures($junit, $magento);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$patterns = array_values(array_filter(
    array_map('trim', file($baselineFile, FILE_IGNORE_NEW_LINES)),
    static fn (string $line): bool => $line !== ''
));

$used = [];
$unlisted = [];
foreach ($found as $key) {
    $matched = false;
    foreach ($patterns as $position => $pattern) {
        if (fnmatch($pattern, $key, FNM_NOESCAPE)) {
            $used[$position] = true;
            $matched = true;
        }
    }
    if (!$matched) {
        $unlisted[] = $key;
    }
}

foreach ($patterns as $position => $pattern) {
    if (!isset($used[$position])) {
        echo "stale baseline entry: $pattern\n";
    }
}
foreach ($unlisted as $key) {
    echo "new failure: $key\n";
}

if ($unlisted === [] && $found === [] && (int)$status !== 0) {
    echo "PHPUnit exited with $status without reporting a failing test\n";
    exit(1);
}

exit($unlisted === [] ? 0 : 1);
