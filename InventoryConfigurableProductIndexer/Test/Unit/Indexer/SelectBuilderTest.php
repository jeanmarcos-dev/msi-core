<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProductIndexer\Test\Unit\Indexer;

use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryConfigurableProductIndexer\Indexer\SelectBuilder;
use Magento\InventoryConfigurationApi\Model\InventoryConfigurationInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SelectBuilderTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var SelectBuilder
     */
    private $selectBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $indexNameBuilder = $this->createMock(IndexNameBuilder::class);
        $indexNameBuilder->method('setIndexId')->willReturnSelf();
        $indexNameBuilder->method('addDimension')->willReturnSelf();
        $indexNameBuilder->method('setAlias')->willReturnSelf();
        $indexNameBuilder->method('build')->willReturn($this->createMock(IndexName::class));

        $indexNameResolver = $this->createMock(IndexNameResolverInterface::class);
        $indexNameResolver->method('resolveName')->willReturn('inventory_stock_2');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('row_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        $statusAttribute = $this->createMock(Attribute::class);
        $statusAttribute->method('getId')->willReturn(97);
        $eavConfig = $this->createMock(Config::class);
        $eavConfig->method('getAttribute')->willReturn($statusAttribute);

        $configuration = $this->createMock(InventoryConfigurationInterface::class);
        $configuration->method('getManageStock')->willReturn(1);

        $this->selectBuilder = new SelectBuilder(
            $resourceConnection,
            $indexNameBuilder,
            $indexNameResolver,
            $metadataPool,
            $eavConfig,
            $configuration
        );
    }

    public function testGetSelectOrdersBySkuAscending(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinInner', 'joinLeft', 'where', 'group'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($select);

        $select->expects(self::once())
            ->method('order')
            ->with('parent_product_entity.sku ASC')
            ->willReturnSelf();

        $this->selectBuilder->getSelect(2, ['configurable_1']);
    }

    public function testStockWithoutWebsitesIgnoresDisabledChildrenAtTheDefaultScope(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');
        $captured = $this->captureSelect();

        $this->selectBuilder->getSelect(2);

        self::assertStringContainsString(
            'MAX(IF(product_status.value = ' . ProductStatus::STATUS_ENABLED . ', stock.is_salable, 0))',
            $captured->columns[IndexStructure::IS_SALABLE]
        );
        self::assertArrayHasKey('product_status', $captured->joinConditions);
        self::assertStringContainsString(
            'product_status.store_id = ' . Store::DEFAULT_STORE_ID,
            $captured->joinConditions['product_status']
        );
        self::assertStringNotContainsString('product_status.value =', $captured->joinConditions['product_status']);
        self::assertStringNotContainsString('EXISTS', $captured->columns[IndexStructure::IS_SALABLE]);
    }

    public function testStockWithWebsitesCountsAChildEnabledInOneOfThem(): void
    {
        $this->connection->method('fetchOne')->willReturn('2');
        $captured = $this->captureSelect();

        $this->selectBuilder->getSelect(2);

        $isSalable = $captured->columns[IndexStructure::IS_SALABLE];
        self::assertStringContainsString('MAX(IF(EXISTS (SELECT 1 FROM store_website sw', $isSalable);
        self::assertStringContainsString("sc.type = 'website' AND sc.stock_id = 2", $isSalable);
        self::assertStringContainsString('sg.group_id = sw.default_group_id', $isSalable);
        self::assertStringContainsString('sv.store_id = sg.default_store_id', $isSalable);
        self::assertStringContainsString('sd.store_id = ' . Store::DEFAULT_STORE_ID, $isSalable);
        self::assertStringContainsString('sv.row_id = product_entity.row_id', $isSalable);
        self::assertStringContainsString('sv.attribute_id = 97', $isSalable);
        self::assertStringContainsString(
            'COALESCE(sv.value, sd.value) = ' . ProductStatus::STATUS_ENABLED,
            $isSalable
        );
        self::assertStringContainsString('stock.is_salable, 0))', $isSalable);
        self::assertArrayNotHasKey('product_status', $captured->joinConditions);
    }

    public function testStockWithWebsitesDoesNotRequireTheChildToBeAssignedToTheWebsite(): void
    {
        $this->connection->method('fetchOne')->willReturn('1');
        $captured = $this->captureSelect();

        $this->selectBuilder->getSelect(2);

        self::assertStringNotContainsString(
            'catalog_product_website',
            $captured->columns[IndexStructure::IS_SALABLE]
        );
        self::assertSame([], array_filter(
            array_keys($captured->joinConditions),
            fn ($alias) => str_contains((string)$alias, 'website')
        ));
    }

    public function testWebsitesOfTheStockAreCountedForTheStockBeingIndexed(): void
    {
        $captured = $this->captureSelect();
        $this->connection->expects(self::once())->method('fetchOne')->willReturn('1');

        $this->selectBuilder->getSelect(2);

        self::assertContains(['stock_id = ?', 2], $captured->conditions);
        self::assertContains(['type = ?', 'website'], $captured->conditions);
    }

    /**
     * Wire a select stub that records the columns and the left join conditions it is given.
     *
     * @return object
     */
    private function captureSelect(): object
    {
        $captured = new class {
            /**
             * @var array
             */
            public array $columns = [];

            /**
             * @var array
             */
            public array $joinConditions = [];

            /**
             * @var array
             */
            public array $conditions = [];
        };

        $select = $this->createMock(Select::class);
        foreach (['joinInner', 'group', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('where')
            ->willReturnCallback(function ($condition, $value = null) use ($select, $captured) {
                $captured->conditions[] = [$condition, $value];
                return $select;
            });
        $select->method('from')
            ->willReturnCallback(function ($table, $cols) use ($select, $captured) {
                if (is_array($cols) && isset($cols[IndexStructure::IS_SALABLE])) {
                    $captured->columns = $cols;
                }
                return $select;
            });
        $select->method('joinLeft')
            ->willReturnCallback(function ($table, $condition) use ($select, $captured) {
                $captured->joinConditions[array_key_first($table)] = $condition;
                return $select;
            });
        $this->connection->method('select')->willReturn($select);

        return $captured;
    }
}
