<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration\Plugin\InventoryApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\InventorySalesApi\Model\ReplaceSalesChannelsForStockInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    Config('cataloginventory/options/show_out_of_stock', '1'),
    DataFixture('Magento_InventoryApi::Test/_files/sources.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stocks.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stock_source_links.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/websites_with_stores.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php'),
    DataFixture('Magento/ConfigurableProduct/_files/configurable_attribute.php'),
    DataFixture('Magento_InventoryConfigurableProductIndexer::Test/_files/product_configurable_multiple.php'),
    DataFixture('Magento_InventoryConfigurableProductIndexer::Test/_files/source_items_configurable_multiple.php'),
]
class ReindexAfterStockSaveTest extends TestCase
{
    private const EU_STOCK = 10;
    private const STALE_QTY = 12345;

    /**
     * @var AdapterInterface
     */
    private $connection;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $this->assignAllProductsToTheMainWebsite();
        $objectManager->get(IndexerRegistry::class)->get(InventoryIndexer::INDEXER_ID)->reindexAll();
    }

    protected function tearDown(): void
    {
        $this->startNewRequest();
        $this->replaceWebsites($this->getDefaultStockId(), ['base']);
    }

    public function testSavingAStockThatDropsAWebsiteMovesItToTheDefaultStockAndReindexesBothStocks(): void
    {
        $defaultStockId = $this->getDefaultStockId();
        $this->saveStockWithWebsites(self::EU_STOCK, ['eu_website', 'base']);
        self::assertSame(self::EU_STOCK, $this->getStockIdOfWebsite('base'));
        $this->makeIndexStale([self::EU_STOCK, $defaultStockId]);

        try {
            $this->saveStockWithWebsites(self::EU_STOCK, ['eu_website']);
        } catch (CouldNotSaveException $exception) {
            self::fail($exception->getMessage() . ': ' . $exception->getPrevious()?->getMessage());
        }

        self::assertSame($defaultStockId, $this->getStockIdOfWebsite('base'));
        self::assertSame(self::EU_STOCK, $this->getStockIdOfWebsite('eu_website'));
        $indexAfterSave = $this->readIndex([self::EU_STOCK, $defaultStockId]);
        Bootstrap::getObjectManager()->get(StockIndexer::class)->executeFull();
        self::assertSame($this->readIndex([self::EU_STOCK, $defaultStockId]), $indexAfterSave);
    }

    public function testReplacingTheChannelsOutsideAStockSaveReindexesTheAffectedStocks(): void
    {
        $defaultStockId = $this->getDefaultStockId();
        $this->makeIndexStale([self::EU_STOCK, $defaultStockId]);

        $this->replaceWebsites(self::EU_STOCK, ['eu_website', 'base']);

        self::assertSame(self::EU_STOCK, $this->getStockIdOfWebsite('base'));
        $indexAfterReplace = $this->readIndex([self::EU_STOCK, $defaultStockId]);
        Bootstrap::getObjectManager()->get(StockIndexer::class)->executeFull();
        self::assertSame($this->readIndex([self::EU_STOCK, $defaultStockId]), $indexAfterReplace);
    }

    private function assignAllProductsToTheMainWebsite(): void
    {
        $this->connection->query(
            'INSERT IGNORE INTO ' . $this->connection->getTableName('catalog_product_website')
            . ' (product_id, website_id) SELECT entity_id, 1 FROM '
            . $this->connection->getTableName('catalog_product_entity')
        );
    }

    private function saveStockWithWebsites(int $stockId, array $websiteCodes): void
    {
        $this->startNewRequest();
        $repository = Bootstrap::getObjectManager()->get(StockRepositoryInterface::class);
        $stock = $repository->get($stockId);
        $stock->getExtensionAttributes()->setSalesChannels($this->createWebsiteChannels($websiteCodes));
        $repository->save($stock);
    }

    private function replaceWebsites(int $stockId, array $websiteCodes): void
    {
        $this->startNewRequest();
        Bootstrap::getObjectManager()->get(ReplaceSalesChannelsForStockInterface::class)
            ->execute($this->createWebsiteChannels($websiteCodes), $stockId);
    }

    private function startNewRequest(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        foreach ([GetStockBySalesChannelInterface::class, GetAssignedStockIdForWebsiteInterface::class] as $type) {
            $cache = $objectManager->get($type);
            if ($cache instanceof ResetAfterRequestInterface) {
                $cache->_resetState();
            }
        }
    }

    private function createWebsiteChannels(array $websiteCodes): array
    {
        $factory = Bootstrap::getObjectManager()->get(SalesChannelInterfaceFactory::class);

        return array_map(
            fn (string $code) => $factory->create(['data' => [
                SalesChannelInterface::CODE => $code,
                SalesChannelInterface::TYPE => SalesChannelInterface::TYPE_WEBSITE,
            ]]),
            $websiteCodes
        );
    }

    private function getDefaultStockId(): int
    {
        return (int)Bootstrap::getObjectManager()->get(DefaultStockProviderInterface::class)->getId();
    }

    private function getStockIdOfWebsite(string $websiteCode): ?int
    {
        $stockId = $this->connection->fetchOne(
            'SELECT stock_id FROM ' . $this->connection->getTableName('inventory_stock_sales_channel')
            . ' WHERE type = ? AND code = ?',
            [SalesChannelInterface::TYPE_WEBSITE, $websiteCode]
        );

        return $stockId === false ? null : (int)$stockId;
    }

    private function makeIndexStale(array $stockIds): void
    {
        foreach ($stockIds as $stockId) {
            $this->connection->update(
                $this->connection->getTableName('inventory_stock_' . $stockId),
                ['quantity' => self::STALE_QTY]
            );
        }
    }

    private function readIndex(array $stockIds): array
    {
        $index = [];
        foreach ($stockIds as $stockId) {
            $index[$stockId] = $this->connection->fetchAll(
                'SELECT * FROM ' . $this->connection->getTableName('inventory_stock_' . $stockId) . ' ORDER BY sku'
            );
        }

        return $index;
    }
}
