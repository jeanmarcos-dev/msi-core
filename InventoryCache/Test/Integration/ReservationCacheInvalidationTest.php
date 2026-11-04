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
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Cache;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReservationCacheInvalidationTest extends TestCase
{
    private const TOPIC = 'inventory.reservations.updateSalabilityStatus';
    private const CHILD = 'cache-contract-child';
    private const OTHER = 'cache-contract-other';
    private const QUANTITY = 10;

    public static function setUpBeforeClass(): void
    {
        Bootstrap::getObjectManager()->get(ClearQueueProcessor::class)->execute(self::TOPIC);
    }

    protected function tearDown(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->delete($resource->getTableName('inventory_reservation'), ['sku = ?' => self::CHILD]);
        $connection->delete($resource->getTableName('inventory_reservation_order_balance'), ['sku = ?' => self::CHILD]);
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        Config('cataloginventory/source_reservations/enabled', '0'),
        DataFixture(AttributeFixture::class, as: 'attribute'),
        DataFixture(ProductFixture::class, ['sku' => self::CHILD], 'child'),
        DataFixture(ProductFixture::class, ['sku' => self::OTHER], 'other'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => self::CHILD, 'source_code' => 'default', 'quantity' => self::QUANTITY, 'status' => 1],
                ['sku' => self::OTHER, 'source_code' => 'default', 'quantity' => self::QUANTITY, 'status' => 1],
            ]
        ),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attribute$'], '_links' => ['$child$']],
            'parent'
        ),
        DataFixture(IndexerFixture::class),
    ]
    public function testConfigurableOnDefaultStock(): void
    {
        $this->assertReservationsRefreshAndCleanOnlyWhatChanged();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        Config('cataloginventory/source_reservations/enabled', '1'),
        DataFixture(AttributeFixture::class, as: 'attribute'),
        DataFixture(ProductFixture::class, ['sku' => self::CHILD], 'child'),
        DataFixture(ProductFixture::class, ['sku' => self::OTHER], 'other'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => self::CHILD, 'source_code' => 'default', 'quantity' => self::QUANTITY, 'status' => 1],
                ['sku' => self::OTHER, 'source_code' => 'default', 'quantity' => self::QUANTITY, 'status' => 1],
            ]
        ),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attribute$'], '_links' => ['$child$']],
            'parent'
        ),
        DataFixture(IndexerFixture::class),
    ]
    public function testConfigurableOnDefaultStockWithSourceReservations(): void
    {
        $this->assertReservationsRefreshAndCleanOnlyWhatChanged();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        Config('cataloginventory/source_reservations/enabled', '0'),
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
    ]
    public function testConfigurableOnCustomStock(): void
    {
        $this->assertReservationsRefreshAndCleanOnlyWhatChanged();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        Config('cataloginventory/source_reservations/enabled', '1'),
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
    ]
    public function testConfigurableOnCustomStockWithSourceReservations(): void
    {
        $this->assertReservationsRefreshAndCleanOnlyWhatChanged();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        Config('cataloginventory/source_reservations/enabled', '0'),
        DataFixture(ProductFixture::class, ['sku' => self::CHILD], 'child'),
        DataFixture(ProductFixture::class, ['sku' => self::OTHER], 'other'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => self::CHILD, 'source_code' => 'default', 'quantity' => self::QUANTITY, 'status' => 1],
                ['sku' => self::OTHER, 'source_code' => 'default', 'quantity' => self::QUANTITY, 'status' => 1],
            ]
        ),
        DataFixture(GroupedProductFixture::class, ['product_links' => ['$child$']], 'parent'),
        DataFixture(IndexerFixture::class),
    ]
    public function testGroupedOnDefaultStock(): void
    {
        $this->assertReservationsRefreshAndCleanOnlyWhatChanged();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
        Config('cataloginventory/source_reservations/enabled', '1'),
        DataFixture(SourceFixture::class, as: 'source'),
        DataFixture(StockFixture::class, as: 'stock'),
        DataFixture(
            StockSourceLinksFixture::class,
            [['stock_id' => '$stock.stock_id$', 'source_code' => '$source.source_code$']]
        ),
        DataFixture(StockSalesChannelsFixture::class, ['stock_id' => '$stock.stock_id$', 'sales_channels' => ['base']]),
        DataFixture(ProductFixture::class, ['sku' => self::CHILD], 'child'),
        DataFixture(ProductFixture::class, ['sku' => self::OTHER], 'other'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => self::CHILD, 'source_code' => '$source.source_code$', 'quantity' => self::QUANTITY],
                ['sku' => self::OTHER, 'source_code' => '$source.source_code$', 'quantity' => self::QUANTITY],
            ]
        ),
        DataFixture(GroupedProductFixture::class, ['product_links' => ['$child$']], 'parent'),
        DataFixture(IndexerFixture::class),
    ]
    public function testGroupedOnCustomStockWithSourceReservations(): void
    {
        $this->assertReservationsRefreshAndCleanOnlyWhatChanged();
    }

    private function assertReservationsRefreshAndCleanOnlyWhatChanged(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $parent = $fixtures->get('parent');
        $watched = [
            (int)$fixtures->get('child')->getId(),
            (int)$parent->getId(),
            (int)$fixtures->get('other')->getId(),
        ];
        $changed = [$watched[0], $watched[1]];
        sort($changed);

        $this->assertStep(
            $parent->getSku(),
            $watched,
            [],
            true,
            ['CACHE-1', -1, SalesEventInterface::EVENT_ORDER_PLACED]
        );
        $this->assertStep(
            $parent->getSku(),
            $watched,
            $changed,
            false,
            ['CACHE-2', -(self::QUANTITY - 1), SalesEventInterface::EVENT_ORDER_PLACED]
        );
        $this->assertStep(
            $parent->getSku(),
            $watched,
            $changed,
            true,
            ['CACHE-2', self::QUANTITY - 1, SalesEventInterface::EVENT_ORDER_CANCELED]
        );
    }

    private function assertStep(
        string $parentSku,
        array $watched,
        array $expectedCleaned,
        bool $expectedSalable,
        array $reservation
    ): void {
        [$incrementId, $quantity, $eventType] = $reservation;
        $canaries = new CacheCanaries($watched);
        $canaries->plant();

        $this->placeReservation($incrementId, (float)$quantity, $eventType);
        Bootstrap::getObjectManager()->get(ConsumerFactory::class)->get(self::TOPIC)->process(10);

        $step = sprintf('%s of %s on %s', $eventType, $quantity, self::CHILD);
        self::assertSame(
            [self::CHILD => $expectedSalable, $parentSku => $expectedSalable],
            $this->indexedSalability([self::CHILD, $parentSku]),
            $step
        );
        self::assertSame(
            ['app' => $expectedCleaned, 'full_page' => $expectedCleaned],
            $canaries->cleanedProductIds(),
            $step
        );
        self::assertSame(['app' => true, 'full_page' => true], $canaries->bystanderKept(), $step);
    }

    private function indexedSalability(array $skus): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $stockId = (int)$objectManager->get(StockResolverInterface::class)
            ->execute(SalesChannelInterface::TYPE_WEBSITE, 'base')
            ->getStockId();
        $rows = $objectManager->get(GetStockItemsDataInterface::class)->execute($skus, $stockId);

        $salability = [];
        foreach ($skus as $sku) {
            $salability[$sku] = (bool)($rows[$sku][GetStockItemsDataInterface::IS_SALABLE] ?? false);
        }

        return $salability;
    }

    private function placeReservation(string $incrementId, float $quantity, string $eventType): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $event = $objectManager->get(SalesEventInterfaceFactory::class)->create([
            'type' => $eventType,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => '0',
        ]);
        $event->setExtensionAttributes(
            $objectManager->get(SalesEventExtensionFactory::class)->create(
                ['data' => ['objectIncrementId' => $incrementId]]
            )
        );
        $channel = $objectManager->get(SalesChannelInterfaceFactory::class)->create(
            ['data' => ['type' => SalesChannelInterface::TYPE_WEBSITE, 'code' => 'base']]
        );
        $item = $objectManager->get(ItemToSellInterfaceFactory::class)->create(
            ['sku' => self::CHILD, 'qty' => $quantity]
        );

        $objectManager->get(PlaceReservationsForSalesEventInterface::class)->execute([$item], $channel, $event);
    }
}
