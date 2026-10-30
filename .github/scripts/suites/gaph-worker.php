<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

[, $workerId, $storeId] = $argv + [null, '0', '1'];
$om = Harness::bootstrap('frontend');
$results = Harness::statePath('gaph-results.txt');
$report = static function (array $result) use ($results): void {
    file_put_contents($results, json_encode($result) . "\n", FILE_APPEND | LOCK_EX);
};
$result = ['worker' => (int)$workerId, 'store' => (int)$storeId, 'reached' => false, 'ok' => false, 'error' => null];

try {
    $store = $om->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore((int)$storeId);
    $product = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class)
        ->get(Harness::SKU_SPLIT, false, $store->getId(), true);
    $quote = $om->create(\Magento\Quote\Model\Quote::class);
    $quote->setStore($store);
    $quote->setCurrency();
    $quote->setCustomerIsGuest(true);
    $quote->setCustomerEmail(Harness::EMAIL);
    $quote->setCustomerFirstname('Suite');
    $quote->setCustomerLastname('Gap');
    $quote->addProduct($product, 1.0);
    $address = [
        'firstname' => 'Suite', 'lastname' => 'Gap', 'street' => '1 Test St', 'city' => 'Testville',
        'country_id' => 'US', 'region_id' => 12, 'postcode' => '90210', 'telephone' => '5555555555',
        'email' => Harness::EMAIL,
    ];
    $quote->getBillingAddress()->addData($address);
    $quote->getShippingAddress()->addData($address);
    $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates()
        ->setShippingMethod('flatrate_flatrate');
    $quote->setInventoryProcessed(false);
    $quote->collectTotals()->save();
    $payment = $quote->getPayment();
    $payment->setQuote($quote);
    $payment->importData(['method' => 'checkmo']);
    $quote->save();
    $cartId = (int)$quote->getId();
} catch (\Throwable $e) {
    $result['error'] = $e->getMessage();
    $report($result);
    exit(0);
}

$barrier = Harness::statePath('gaph-go');
$deadline = time() + 30;
while (!file_exists($barrier) && time() < $deadline) {
    usleep(2000);
}
$result['reached'] = file_exists($barrier);

try {
    $orderId = $om->get(\Magento\Quote\Api\CartManagementInterface::class)->placeOrder($cartId);
    $result['ok'] = (bool)$orderId;
} catch (\Throwable $e) {
    $result['error'] = $e->getMessage();
}
$report($result);
