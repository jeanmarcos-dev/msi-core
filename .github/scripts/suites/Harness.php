<?php
declare(strict_types=1);

namespace Suites;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManagerInterface;

class Harness
{
    public const EMAIL = 'suite@test.local';

    public const SKU_SPLIT = 'SLR-1';

    public const SKU_ABUNDANT = 'SLR-2';

    public const SKU_SMALL = 'SLR-3';

    public const STOCK_NAME = 'SLR Stock';

    public const SIBLING_STOCK_NAME = 'SLR Sibling';

    public const SECOND_WEBSITE_CODE = 'slrw2';

    public static function root(): string
    {
        $root = getenv('MAGENTO_ROOT');
        if ($root === false || $root === '') {
            fwrite(STDERR, "MAGENTO_ROOT is not set\n");
            exit(2);
        }

        return rtrim($root, '/');
    }

    public static function bootstrap(string $area): ObjectManagerInterface
    {
        require_once self::root() . '/app/bootstrap.php';

        $objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        try {
            $objectManager->get(State::class)->setAreaCode($area);
        } catch (\Exception $e) {
            unset($e);
        }

        return $objectManager;
    }

    public static function resource(ObjectManagerInterface $om): ResourceConnection
    {
        return $om->get(ResourceConnection::class);
    }

    public static function stockId(ObjectManagerInterface $om, string $name): int
    {
        return (int)self::resource($om)->getConnection()->fetchOne(
            'SELECT stock_id FROM inventory_stock WHERE name = ?',
            [$name]
        );
    }

    public static function statePath(string $name): string
    {
        $directory = self::root() . '/var/suites';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory . '/' . $name;
    }

    public static function placeOrder(ObjectManagerInterface $om, string $sku, float $qty, ?int $storeId = null)
    {
        $storeManager = $om->get(\Magento\Store\Model\StoreManagerInterface::class);
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $store = $storeId ? $storeManager->getStore($storeId) : $storeManager->getDefaultStoreView();
        $product = $productRepo->get($sku, false, $store->getId(), true);
        $quote = $om->create(\Magento\Quote\Model\Quote::class);
        $quote->setStore($store);
        $quote->setCurrency();
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerEmail(self::EMAIL);
        $quote->setCustomerFirstname('Suite');
        $quote->setCustomerLastname('Test');
        $quote->addProduct($product, $qty);
        $address = [
            'firstname' => 'Suite',
            'lastname' => 'Test',
            'street' => '1 Test St',
            'city' => 'Testville',
            'country_id' => 'US',
            'region_id' => 12,
            'postcode' => '90210',
            'telephone' => '5555555555',
            'email' => self::EMAIL,
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

        return $om->get(\Magento\Quote\Model\QuoteManagement::class)->submit($quote);
    }

    public static function shipOrder(
        ObjectManagerInterface $om,
        $order,
        ?string $sourceCode,
        array $itemQtys = []
    ): void {
        $arguments = $om->get(\Magento\Sales\Api\Data\ShipmentCreationArgumentsInterfaceFactory::class)->create();
        $arguments->setExtensionAttributes(
            $om->get(\Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory::class)
                ->create()
                ->setSourceCode($sourceCode)
        );
        $items = [];
        if ($itemQtys) {
            $itemFactory = $om->get(\Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory::class);
            foreach ($order->getAllItems() as $orderItem) {
                if (isset($itemQtys[$orderItem->getSku()])) {
                    $item = $itemFactory->create();
                    $item->setOrderItemId($orderItem->getItemId());
                    $item->setQty($itemQtys[$orderItem->getSku()]);
                    $items[] = $item;
                }
            }
        }
        $om->get(\Magento\Sales\Api\ShipOrderInterface::class)
            ->execute((int)$order->getEntityId(), $items, false, false, null, [], [], $arguments);
    }

    public static function setSourceQty(ObjectManagerInterface $om, string $sku, array $bySource): void
    {
        $factory = $om->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class);
        $items = [];
        foreach ($bySource as $code => $qty) {
            $item = $factory->create();
            $item->setSku($sku);
            $item->setSourceCode($code);
            $item->setQuantity((float)$qty);
            $item->setStatus(1);
            $items[] = $item;
        }
        $om->get(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class)->execute($items);
    }

    public static function salable(ObjectManagerInterface $om, string $sku, int $stockId): float
    {
        try {
            return (float)$om->get(\Magento\InventorySalesApi\Api\GetProductSalableQtyInterface::class)
                ->execute($sku, $stockId);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public static function stockIndexQty(ObjectManagerInterface $om, string $sku, int $stockId): ?float
    {
        $connection = self::resource($om)->getConnection();
        $table = $om->get(\Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface::class)
            ->execute($stockId);
        try {
            $qty = $connection->fetchOne(
                $connection->select()->from($table, ['quantity'])->where('sku = ?', $sku)->limit(1)
            );
        } catch (\Throwable $e) {
            return null;
        }

        return $qty === false ? null : (float)$qty;
    }

    public static function assignChannel(ObjectManagerInterface $om, string $websiteCode, int $stockId): void
    {
        $stockRepo = $om->get(\Magento\InventoryApi\Api\StockRepositoryInterface::class);
        $channel = $om->get(\Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory::class)->create();
        $channel->setType('website');
        $channel->setCode($websiteCode);
        $stock = $stockRepo->get($stockId);
        $stock->getExtensionAttributes()->setSalesChannels([$channel]);
        $stockRepo->save($stock);
        $om->get(\Magento\Framework\App\Cache\TypeListInterface::class)->cleanType('config');
        $om->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
    }

    public static function reindexInventory(ObjectManagerInterface $om): void
    {
        $om->get(\Magento\Framework\Indexer\IndexerRegistry::class)->get('inventory')->reindexAll();
    }

    public static function setConfig(ObjectManagerInterface $om, string $path, string $value): void
    {
        $om->get(\Magento\Framework\App\Config\Storage\WriterInterface::class)->save($path, $value);
        $om->get(\Magento\Framework\App\Cache\TypeListInterface::class)->cleanType('config');
        $om->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
    }

    public static function openOrderIds(ObjectManagerInterface $om): array
    {
        $connection = self::resource($om)->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->quoteInto('SELECT entity_id FROM sales_order WHERE customer_email = ?', self::EMAIL)
            . ' AND state NOT IN ' . $connection->quoteInto('(?)', ['canceled', 'complete', 'closed'])
        ));
    }

    public static function cancelOpenOrders(ObjectManagerInterface $om): array
    {
        $management = $om->get(\Magento\Sales\Api\OrderManagementInterface::class);
        $ids = self::openOrderIds($om);
        $canceled = 0;
        $errors = 0;
        $start = microtime(true);
        foreach ($ids as $id) {
            try {
                $management->cancel($id);
                $canceled++;
            } catch (\Throwable $e) {
                $errors++;
                fwrite(STDERR, $id . ': ' . $e->getMessage() . "\n");
            }
        }

        return [
            'canceled' => $canceled,
            'errors' => $errors,
            'avg_ms' => $ids ? round((microtime(true) - $start) * 1000 / count($ids), 1) : 0.0,
        ];
    }
}
