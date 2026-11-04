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
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
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
class SourceItemCacheInvalidationTest extends TestCase
{
    private const CHILD = 'cache-source-child';
    private const OTHER = 'cache-source-other';
    private const QUANTITY = 10;

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
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
        $this->assertSourceItemSavesCleanOnlyWhatChanged('default');
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
    ]
    public function testConfigurableOnCustomStock(): void
    {
        $this->assertSourceItemSavesCleanOnlyWhatChanged(
            DataFixtureStorageManager::getStorage()->get('source')->getSourceCode()
        );
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Cache('full_page', true),
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
        $this->assertSourceItemSavesCleanOnlyWhatChanged('default');
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
    ]
    public function testGroupedOnCustomStock(): void
    {
        $this->assertSourceItemSavesCleanOnlyWhatChanged(
            DataFixtureStorageManager::getStorage()->get('source')->getSourceCode()
        );
    }

    private function assertSourceItemSavesCleanOnlyWhatChanged(string $sourceCode): void
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
            [$sourceCode, 5, SourceItemInterface::STATUS_IN_STOCK]
        );
        $this->assertStep(
            $parent->getSku(),
            $watched,
            $changed,
            false,
            [$sourceCode, 0, SourceItemInterface::STATUS_OUT_OF_STOCK]
        );
        $this->assertStep(
            $parent->getSku(),
            $watched,
            $changed,
            true,
            [$sourceCode, self::QUANTITY, SourceItemInterface::STATUS_IN_STOCK]
        );
    }

    private function assertStep(
        string $parentSku,
        array $watched,
        array $expectedCleaned,
        bool $expectedSalable,
        array $sourceItem
    ): void {
        [$sourceCode, $quantity, $status] = $sourceItem;
        $canaries = new CacheCanaries($watched);
        $canaries->plant();

        $this->saveSourceItem($sourceCode, (float)$quantity, (int)$status);

        $step = sprintf('%s at %s on %s, status %d', self::CHILD, $quantity, $sourceCode, $status);
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

    private function saveSourceItem(string $sourceCode, float $quantity, int $status): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $sourceItem = $objectManager->get(SourceItemInterfaceFactory::class)->create();
        $sourceItem->setSku(self::CHILD);
        $sourceItem->setSourceCode($sourceCode);
        $sourceItem->setQuantity($quantity);
        $sourceItem->setStatus($status);

        $objectManager->get(SourceItemsSaveInterface::class)->execute([$sourceItem]);
    }
}
