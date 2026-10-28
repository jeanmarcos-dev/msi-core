<?php
declare(strict_types=1);

$root = rtrim($argv[1] ?? getcwd(), '/');

function packageOf(string $namespace): ?string
{
    if ($namespace === 'Framework') {
        return 'magento/framework';
    }
    if (in_array($namespace, ['TestFramework', 'Setup'], true)) {
        return null;
    }

    return 'magento/module-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $namespace));
}

function namespacesInPhp(string $file): array
{
    $found = [];
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)
            && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
            && preg_match('/^\\\\?Magento\\\\([A-Za-z0-9]+)\\\\/', $token[1], $match)
        ) {
            $found[$match[1]] = true;
        }
    }

    return array_keys($found);
}

function namespacesInXml(string $file): array
{
    preg_match_all('/(?<![A-Za-z0-9\\\\])\\\\?Magento\\\\([A-Z][A-Za-z0-9]*)\\\\/', file_get_contents($file), $matches);

    return array_values(array_unique($matches[1]));
}

function sourceFiles(string $module): iterable
{
    $files = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($module, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => !($file->isDir() && $file->getFilename() === 'Test')
        )
    );
    foreach ($files as $file) {
        if (in_array($file->getExtension(), ['php', 'phtml', 'xml'], true)) {
            yield $file->getPathname();
        }
    }
}

$failures = [];
foreach (glob($root . '/*/registration.php') as $registration) {
    $module = dirname($registration);
    $composer = json_decode(file_get_contents($module . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    $declared = array_keys(($composer['require'] ?? []) + ($composer['suggest'] ?? []));
    $self = $composer['name'];
    $used = [];
    foreach (sourceFiles($module) as $file) {
        $namespaces = str_ends_with($file, '.xml') ? namespacesInXml($file) : namespacesInPhp($file);
        foreach ($namespaces as $namespace) {
            $package = packageOf($namespace);
            if ($package !== null && $package !== $self && !in_array($package, $declared, true)) {
                $used[$package][] = substr($file, strlen($root) + 1);
            }
        }
    }
    ksort($used);
    foreach ($used as $package => $files) {
        $failures[] = sprintf('%s uses %s without declaring it (%s)', $self, $package, implode(', ', array_unique($files)));
    }
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Every module declares the modules it uses.\n";
