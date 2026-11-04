<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Test\Integration;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\Indexer\Test\Fixture\ScheduleMode as ScheduleModeFixture;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Cache;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReconcileSweepCacheInvalidationTest extends TestCase
{
    private const TOPIC = 'inventory.reservations.updateSalabilityStatus';
    private const CHILD = 'cache-sweep-child';
    private const OTHER = 'cache-sweep-other';
    private const INCREMENT_ID = 'CACHE-SWEEP-1';
    private const QUANTITY = 10;

    public static function setUpBeforeClass(): void
    {
        Bootstrap::getObjectManager()->get(ClearQueueProcessor::class)->execute(self::TOPIC);
    }

    protected function tearDown(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->delete($resource->getTableName('sales_order'), ['increment_id = ?' => self::INCREMENT_ID]);
        $connection->delete($resource->getTableName('inventory_reservation'), ['sku = ?' => self::CHILD]);
        $connection->delete($resource->getTableName('inventory_reservation_order_balance'), ['sku = ?' => self::CHILD]);
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        DataFixture(SourceFixture::class, as: 'source'),
        DataFixture(StockFixture::class, as: 'stock'),
        DataFixture(
            StockSourceLinksFixture::class,
            [['stock_id' => '$stock.stock_id$', 'source_code' => '$source.source_code$']]
        ),
        DataFixture(StockSalesChannelsFixture::class, ['stock_id' => '$stock.stock_id$', 'sales_channels' => ['base']]),
        DataFixture(AttributeFixture::class, as: 'attribute'),
        DataFixture(ProductFixture::class, ['sku' => self::CHILD], 'child'),
        DataFixture(ProductFixture::class, ['sku' => self::OTHER], 'other'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => self::CHILD, 'source_code' => '$source.source_code$', 'quantity' => self::QUANTITY],
                ['sku' => self::OTHER, 'source_code' => '$source.source_code$', 'quantity' => self::QUANTITY],
            ]
        ),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attribute$'], '_links' => ['$child$']],
            'parent'
        ),
        DataFixture(IndexerFixture::class),
        DataFixture(ScheduleModeFixture::class, ['indexer' => 'catalog_product_price']),
    ]
    public function testTheSweepCleansTheProductsItMakesSalableAgain(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $parentSku = $fixtures->get('parent')->getSku();
        $stockId = (int)$fixtures->get('stock')->getStockId();
        $watched = [
            (int)$fixtures->get('child')->getId(),
            (int)$fixtures->get('parent')->getId(),
            (int)$fixtures->get('other')->getId(),
        ];
        $changed = [$watched[0], $watched[1]];
        sort($changed);

        $this->insertCompleteOrder();
        $this->reserveTheWholeStockOfTheChild($stockId);
        $objectManager->get(StockIndexer::class)->executeList([$stockId]);
        self::assertSame(
            [self::CHILD => false, $parentSku => false],
            (new IndexedSalability())->onBaseWebsite([self::CHILD, $parentSku])
        );

        $canaries = new CacheCanaries($watched);
        $canaries->plant();

        $result = $objectManager->get(ReconcileReservationsSweep::class)->execute(500);
        $objectManager->get(ConsumerFactory::class)->get(self::TOPIC)->process(10);

        self::assertSame([$stockId], $result['stock_ids']);
        self::assertSame(
            [self::CHILD => true, $parentSku => true],
            (new IndexedSalability())->onBaseWebsite([self::CHILD, $parentSku])
        );
        self::assertSame(['app' => $changed, 'full_page' => $changed], $canaries->cleanedProductIds());
        self::assertSame(['app' => true, 'full_page' => true], $canaries->bystanderKept());
    }

    private function insertCompleteOrder(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->insert(
            $resource->getTableName('sales_order'),
            ['state' => Order::STATE_COMPLETE, 'status' => 'complete', 'increment_id' => self::INCREMENT_ID]
        );
    }

    private function reserveTheWholeStockOfTheChild(int $stockId): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $reservation = $objectManager->get(ReservationBuilderInterface::class)
            ->setSku(self::CHILD)
            ->setQuantity(-self::QUANTITY)
            ->setStockId($stockId)
            ->setObjectIncrementId(self::INCREMENT_ID)
            ->setMetadata(json_encode([
                'event_type' => 'order_placed',
                'object_type' => 'order',
                'object_id' => '',
                'object_increment_id' => self::INCREMENT_ID,
            ]))
            ->build();
        $objectManager->get(AppendReservationsInterface::class)->execute([$reservation]);
    }
}
