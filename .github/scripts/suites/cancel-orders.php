<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$om = Harness::bootstrap('frontend');
$result = Harness::cancelOpenOrders($om);
printf("canceled=%d errors=%d avg_ms=%.1f\n", $result['canceled'], $result['errors'], $result['avg_ms']);
exit($result['errors'] ? 1 : 0);
