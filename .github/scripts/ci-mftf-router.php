<?php
declare(strict_types=1);

$magentoRoot = getenv('MAGENTO_ROOT');
if ($magentoRoot === false || $magentoRoot === '') {
    fwrite(STDERR, "MAGENTO_ROOT is not set\n");
    exit(1);
}

$cliEndpoint = '/dev/tests/acceptance/utils/command.php';
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $cliEndpoint) {
    chdir($magentoRoot . dirname($cliEndpoint));
    require $magentoRoot . $cliEndpoint;
    return true;
}

return require $magentoRoot . '/phpserver/router.php';
