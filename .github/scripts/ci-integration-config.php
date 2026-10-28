<?php
declare(strict_types=1);

[, $integrationDir, $sourceDir] = $argv + [null, null, null];
if ($integrationDir === null || $sourceDir === null) {
    fwrite(STDERR, "usage: ci-integration-config.php <magento>/dev/tests/integration <msi-core checkout>\n");
    exit(2);
}

$setting = static fn (string $name, string $default): string => getenv($name) === false ? $default : getenv($name);
$settings = [
    'db-host' => $setting('DB_HOST', '127.0.0.1'),
    'db-user' => $setting('DB_USER', 'root'),
    'db-password' => $setting('DB_PASSWORD', 'root'),
    'db-name' => $setting('DB_NAME', 'magento_integration_tests'),
    'opensearch-host' => $setting('OPENSEARCH_HOST', '127.0.0.1'),
    'opensearch-port' => (int)$setting('OPENSEARCH_PORT', '9200'),
];

$installConfig = file_get_contents($integrationDir . '/etc/install-config-mysql.php.dist');
$installConfig = preg_replace("/^\s*'amqp-[^']+'\s*=>.*\n/m", '', $installConfig);
foreach ($settings as $key => $value) {
    $pattern = '/^(\s*' . preg_quote(var_export($key, true), '/') . '\s*=>\s*).*,\s*$/m';
    $count = 0;
    $installConfig = preg_replace($pattern, '${1}' . var_export($value, true) . ',', $installConfig, 1, $count);
    if ($count !== 1) {
        fwrite(STDERR, "install-config-mysql.php.dist has no '$key' entry\n");
        exit(1);
    }
}
file_put_contents($integrationDir . '/etc/install-config-mysql.php', $installConfig);

$phpunit = new DOMDocument();
$phpunit->preserveWhiteSpace = false;
$phpunit->formatOutput = true;
$phpunit->load($integrationDir . '/phpunit.xml.dist');
$testsuites = $phpunit->getElementsByTagName('testsuites')->item(0);
while ($testsuites->firstChild) {
    $testsuites->removeChild($testsuites->firstChild);
}
$testsuite = $phpunit->createElement('testsuite');
$testsuite->setAttribute('name', 'msi-core integration');
$directory = $phpunit->createElement('directory', rtrim($sourceDir, '/') . '/*/Test/Integration');
$directory->setAttribute('suffix', 'Test.php');
$testsuite->appendChild($directory);
$testsuites->appendChild($testsuite);
$phpunit->save($integrationDir . '/phpunit.xml');
