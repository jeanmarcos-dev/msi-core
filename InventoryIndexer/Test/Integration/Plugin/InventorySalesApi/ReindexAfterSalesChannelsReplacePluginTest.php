<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration\Plugin\InventorySalesApi;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Plugin\InventorySalesApi\ReindexAfterSalesChannelsReplacePlugin;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Model\ReplaceSalesChannelsForStockInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ReindexAfterSalesChannelsReplacePlugin::class),
    DbIsolation(false),
    DataFixture('Magento_InventoryApi::Test/_files/sources.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stocks.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stock_source_links.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/websites_with_stores.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php'),
    DataFixture('Magento/ConfigurableProduct/_files/configurable_attribute.php'),
    DataFixture('Magento_InventoryConfigurableProductIndexer::Test/_files/product_configurable_multiple.php'),
    DataFixture('Magento_InventoryConfigurableProductIndexer::Test/_files/source_items_configurable_multiple.php'),
]
class ReindexAfterSalesChannelsReplacePluginTest extends TestCase
{
    private const SKU = 'configurable_1';
    private const EU_STOCK = 10;
    private const GLOBAL_STOCK = 30;

    /**
     * @var AdapterInterface
     */
    private $connection;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $this->disableChildrenInTheGlobalWebsite();
        $objectManager->get(StockIndexer::class)->executeFull();
    }

    public function testStockIsReindexedWhenItsWebsitesAreReplaced(): void
    {
        $this->markInventoryIndexAsBuilt();
        self::assertSame(1, $this->isSalable(self::EU_STOCK));

        $this->replaceChannels(self::EU_STOCK, ['global_website']);

        self::assertSame(0, $this->isSalable(self::EU_STOCK));
    }

    public function testStockIsLeftAloneWhileTheInventoryIndexIsNotBuilt(): void
    {
        Bootstrap::getObjectManager()->get(IndexerRegistry::class)->get(InventoryIndexer::INDEXER_ID)->invalidate();
        self::assertSame(1, $this->isSalable(self::EU_STOCK));

        $this->replaceChannels(self::EU_STOCK, ['global_website']);

        self::assertSame(1, $this->isSalable(self::EU_STOCK));
    }

    public function testStockIsNotReindexedWhenItsWebsitesStayTheSame(): void
    {
        $this->markInventoryIndexAsBuilt();
        $this->connection->update(
            'inventory_stock_' . self::EU_STOCK,
            ['is_salable' => 0],
            ['sku = ?' => self::SKU]
        );

        $this->replaceChannels(self::EU_STOCK, ['eu_website']);

        self::assertSame(0, $this->isSalable(self::EU_STOCK));
    }

    private function replaceChannels(int $stockId, array $websiteCodes): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $factory = $objectManager->get(SalesChannelInterfaceFactory::class);
        $channels = array_map(
            fn (string $code) => $factory->create(['data' => [
                SalesChannelInterface::CODE => $code,
                SalesChannelInterface::TYPE => SalesChannelInterface::TYPE_WEBSITE,
            ]]),
            $websiteCodes
        );
        $objectManager->get(ReplaceSalesChannelsForStockInterface::class)->execute($channels, $stockId);
    }

    private function markInventoryIndexAsBuilt(): void
    {
        Bootstrap::getObjectManager()->get(IndexerRegistry::class)->get(InventoryIndexer::INDEXER_ID)->reindexAll();
    }

    private function isSalable(int $stockId): int
    {
        return (int)$this->connection->fetchOne(
            'SELECT is_salable FROM ' . $this->connection->getTableName('inventory_stock_' . $stockId)
            . ' WHERE sku = ?',
            [self::SKU]
        );
    }

    private function disableChildrenInTheGlobalWebsite(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $linkField = $objectManager->get(MetadataPool::class)->getMetadata(ProductInterface::class)->getLinkField();
        $storeId = (int)$this->connection->fetchOne(
            'SELECT sg.default_store_id FROM store_website sw'
            . ' JOIN store_group sg ON sg.group_id = sw.default_group_id WHERE sw.code = \'global_website\''
        );
        $statusId = (int)$objectManager->get(Config::class)->getAttribute('catalog_product', 'status')->getId();
        foreach (['simple_11', 'simple_21'] as $sku) {
            $this->connection->insertOnDuplicate('catalog_product_entity_int', [
                'attribute_id' => $statusId,
                'store_id' => $storeId,
                $linkField => $this->connection->fetchOne(
                    "SELECT $linkField FROM catalog_product_entity WHERE sku = ?",
                    [$sku]
                ),
                'value' => Status::STATUS_DISABLED,
            ], ['value']);
        }
    }
}
