<?php
declare(strict_types=1);

$usage = "usage: ci-webapi-config.php <magento>/dev/tests/api-functional <msi-core checkout> rest|soap|graphql\n";
[, $apiDir, $sourceDir, $adapter] = $argv + [null, null, null, null];
$directories = ['rest' => 'Api', 'soap' => 'Api', 'graphql' => 'GraphQl'];
if ($apiDir === null || $sourceDir === null || !isset($directories[$adapter])) {
    fwrite(STDERR, $usage);
    exit(2);
}

$baseUrl = getenv('WEBAPI_BASE_URL') === false ? 'http://127.0.0.1:8080' : getenv('WEBAPI_BASE_URL');

$phpunit = new DOMDocument();
$phpunit->preserveWhiteSpace = false;
$phpunit->formatOutput = true;
$phpunit->load($apiDir . '/phpunit_' . $adapter . '.xml.dist');

$baseUrlSet = false;
foreach ($phpunit->getElementsByTagName('const') as $constant) {
    if ($constant->getAttribute('name') === 'TESTS_BASE_URL') {
        $constant->setAttribute('value', $baseUrl);
        $baseUrlSet = true;
    }
}
if (!$baseUrlSet) {
    fwrite(STDERR, "phpunit_$adapter.xml.dist has no TESTS_BASE_URL constant\n");
    exit(1);
}

$testsuites = $phpunit->getElementsByTagName('testsuites')->item(0);
while ($testsuites->firstChild) {
    $testsuites->removeChild($testsuites->firstChild);
}
$testsuite = $phpunit->createElement('testsuite');
$testsuite->setAttribute('name', 'msi-core web API ' . $adapter);
$directory = $phpunit->createElement('directory', rtrim($sourceDir, '/') . '/*/Test/' . $directories[$adapter]);
$directory->setAttribute('suffix', 'Test.php');
$testsuite->appendChild($directory);
$testsuites->appendChild($testsuite);
$phpunit->save($apiDir . '/phpunit_' . $adapter . '.xml');
