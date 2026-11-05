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
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\Indexer\Test\Fixture\ScheduleMode as ScheduleModeFixture;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Cache;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class StockReindexCacheInvalidationTest extends TestCase
{
    private const CHILD = 'cache-stock-child';
    private const OTHER = 'cache-stock-other';
    private const QUANTITY = 10;

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
    public function testConfigurableOnCustomStock(): void
    {
        $this->assertStockReindexCleansOnlyWhatChanged();
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
        DataFixture(ScheduleModeFixture::class, ['indexer' => 'catalog_product_price']),
    ]
    public function testGroupedOnCustomStock(): void
    {
        $this->assertStockReindexCleansOnlyWhatChanged();
    }

    private function assertStockReindexCleansOnlyWhatChanged(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $parentSku = $fixtures->get('parent')->getSku();
        $watched = [
            (int)$fixtures->get('child')->getId(),
            (int)$fixtures->get('parent')->getId(),
            (int)$fixtures->get('other')->getId(),
        ];
        $changed = [$watched[0], $watched[1]];
        sort($changed);

        $this->assertStep($parentSku, $watched, [], true, [self::QUANTITY, 1]);
        $this->assertStep($parentSku, $watched, $changed, false, [0, 0]);
        $this->assertStep($parentSku, $watched, $changed, true, [self::QUANTITY, 1]);
    }

    private function assertStep(
        string $parentSku,
        array $watched,
        array $expectedCleaned,
        bool $expectedSalable,
        array $sourceItem
    ): void {
        [$quantity, $status] = $sourceItem;
        $fixtures = DataFixtureStorageManager::getStorage();
        $this->writeSourceItemBehindTheIndexer($fixtures->get('source')->getSourceCode(), $quantity, $status);

        $canaries = new CacheCanaries($watched);
        $canaries->plant();

        Bootstrap::getObjectManager()->get(StockIndexer::class)
            ->executeList([(int)$fixtures->get('stock')->getStockId()]);

        $step = sprintf('%s at %s, status %d, then the stock reindexed', self::CHILD, $quantity, $status);
        self::assertSame(
            [self::CHILD => $expectedSalable, $parentSku => $expectedSalable],
            (new IndexedSalability())->onBaseWebsite([self::CHILD, $parentSku]),
            $step
        );
        self::assertSame(
            ['app' => $expectedCleaned, 'full_page' => $expectedCleaned],
            $canaries->cleanedProductIds(),
            $step
        );
        self::assertSame(['app' => true, 'full_page' => true], $canaries->bystanderKept(), $step);
    }

    private function writeSourceItemBehindTheIndexer(string $sourceCode, int $quantity, int $status): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('inventory_source_item'),
            ['quantity' => $quantity, 'status' => $status],
            ['sku = ?' => self::CHILD, 'source_code = ?' => $sourceCode]
        );
    }
}
