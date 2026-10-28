<?php
declare(strict_types=1);

[, $probeDir, $product, $repository, $sourceDir] = $argv + [null, null, null, null, null];
if ($sourceDir === null) {
    fwrite(STDERR, "usage: ci-newest-release.php <empty dir> <product package> <composer repository> <msi-core checkout>\n");
    exit(2);
}

file_put_contents($probeDir . '/composer.json', json_encode([
    'repositories' => [
        ['type' => 'composer', 'url' => $repository],
        ['type' => 'path', 'url' => $sourceDir],
    ],
    'require' => [
        $product => '*',
        'jeanmarcos/msi-core' => '*@dev',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

passthru(
    'composer update --working-dir=' . escapeshellarg($probeDir)
    . ' --no-install --no-plugins --no-scripts --no-interaction --no-progress 1>&2',
    $exitCode
);
if ($exitCode !== 0) {
    exit($exitCode);
}

$lock = json_decode(file_get_contents($probeDir . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
foreach ($lock['packages'] as $package) {
    if ($package['name'] === $product) {
        echo $package['version'], "\n";
        exit(0);
    }
}
fwrite(STDERR, "$product is not in the resolved lock\n");
exit(1);
