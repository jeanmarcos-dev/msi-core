<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Test\Unit\Model\ResourceModel\Indexer;

use Magento\Bundle\Model\ResourceModel\Indexer\SelectionPriceModifierInterface;
use Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\InventoryBundleProductIndexer\Model\ResourceModel\Indexer\Price;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PriceTest extends TestCase
{
    private const CONNECTION_NAME = 'indexer';

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var StockConfigurationInterface|MockObject
     */
    private $stockConfiguration;

    /**
     * @var SelectionPriceModifierInterface|MockObject
     */
    private $selectionPriceIndexer;

    /**
     * @var string[]
     */
    private array $conditions = [];

    /**
     * @var string[]
     */
    private array $joinedTables = [];

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('getCheckSql')->willReturn('check');
        $this->connection->method('getIfNullSql')->willReturn('ifnull');
        $this->connection->method('getLeastSql')->willReturn('least');
        $this->connection->method('quoteIdentifier')->willReturnArgument(0);
        $this->connection->method('select')->willReturnCallback(fn () => $this->createSelectMock());

        $this->stockConfiguration = $this->createMock(StockConfigurationInterface::class);
        $this->selectionPriceIndexer = $this->createMock(SelectionPriceModifierInterface::class);
    }

    public function testTheStockFilterKeepsUnsalableSelectionsOutOfThePriceRange(): void
    {
        $this->stockConfiguration->method('isShowOutOfStock')->willReturn(false);

        $this->calculateDynamicBundleSelectionPrice();

        $this->assertContains('inventory_stock_7', $this->joinedTables);
        $this->assertContains('si.is_salable = ?', $this->conditions);
    }

    public function testShowingOutOfStockProductsPricesEverySelection(): void
    {
        $this->stockConfiguration->method('isShowOutOfStock')->willReturn(true);

        $this->calculateDynamicBundleSelectionPrice();

        $this->assertNotContains('inventory_stock_7', $this->joinedTables);
        $this->assertNotContains('si.is_salable = ?', $this->conditions);
    }

    public function testTheCoreModifierGetsToPruneWhatASalableBundleCannotBeBoughtWith(): void
    {
        $this->stockConfiguration->method('isShowOutOfStock')->willReturn(true);
        $this->selectionPriceIndexer->expects($this->once())
            ->method('modify')
            ->with('catalog_product_index_price_bundle_sel_temp', []);

        $priceTable = $this->createMock(IndexTableStructure::class);
        $priceTable->method('getTableName')->willReturn('price_tmp');

        $price = $this->createPrice();
        (new \ReflectionMethod($price, 'calculateBundleOptionPrice'))->invoke($price, $priceTable, []);
    }

    /**
     * Run the method under test, recording what it joined and what it filtered on.
     */
    private function calculateDynamicBundleSelectionPrice(): void
    {
        $this->invoke('calculateDynamicBundleSelectionPrice');
    }

    private function invoke(string $method): void
    {
        $price = $this->createPrice();
        (new \ReflectionMethod($price, $method))->invoke($price, []);
    }

    private function createSelectMock(): Select
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('columns')->willReturnSelf();
        $select->method('group')->willReturnSelf();
        $select->method('crossUpdateFromSelect')->willReturn('UPDATE');
        $select->method('__toString')->willReturn('SELECT 1');
        $recordJoin = function ($table) use ($select) {
            $this->joinedTables[] = is_array($table) ? current($table) : $table;
            return $select;
        };
        $select->method('join')->willReturnCallback($recordJoin);
        $select->method('joinInner')->willReturnCallback($recordJoin);
        $select->method('where')->willReturnCallback(
            function ($condition) use ($select) {
                $this->conditions[] = $condition;
                return $select;
            }
        );

        return $select;
    }

    private function createPrice(): Price
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->with(self::CONNECTION_NAME)->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $website->method('getCode')->willReturn('base');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsites')->willReturn([$website]);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(7);
        $stockResolver = $this->createMock(StockResolverInterface::class);
        $stockResolver->method('execute')->willReturn($stock);

        $stockIndexTableNameResolver = $this->createMock(StockIndexTableNameResolverInterface::class);
        $stockIndexTableNameResolver->method('execute')->with(7)->willReturn('inventory_stock_7');

        return new Price(
            $this->createMock(IndexTableStructureFactory::class),
            $this->createMock(TableMaintainer::class),
            $metadataPool,
            $resource,
            $this->createMock(BasePriceModifier::class),
            $this->createMock(JoinAttributeProcessor::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(Manager::class),
            $stockIndexTableNameResolver,
            $stockResolver,
            $storeManager,
            $this->stockConfiguration,
            $this->selectionPriceIndexer,
            false,
            self::CONNECTION_NAME
        );
    }
}
