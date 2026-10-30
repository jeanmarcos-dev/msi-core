<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogSearchConfigurableProduct\Test\Unit\Model\CatalogSearch\Indexer;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\CatalogInventory\Model\ResourceModel\StockStatusFilterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryCatalogSearchConfigurableProduct\Model\CatalogSearch\Indexer\ConfigurableChildStockStatusModifier;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurableChildStockStatusModifierTest extends TestCase
{
    private const STORE_ID = 4;
    private const WEBSITE_ID = 7;

    /**
     * @var StockStatusFilterInterface|MockObject
     */
    private $stockStatusFilter;

    /**
     * @var object
     */
    private $existsSelect;

    /**
     * @var ConfigurableChildStockStatusModifier
     */
    private ConfigurableChildStockStatusModifier $modifier;

    protected function setUp(): void
    {
        $this->existsSelect = new class {
            /**
             * @var array
             */
            public array $joins = [];

            /**
             * @var array
             */
            public array $leftJoins = [];

            /**
             * @var array
             */
            public array $conditions = [];
        };
        $captured = $this->existsSelect;
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnCallback(function ($table, $condition) use ($select, $captured) {
            $captured->joins[array_key_first($table)] = $condition;
            return $select;
        });
        $select->method('joinLeft')->willReturnCallback(function ($table, $condition) use ($select, $captured) {
            $captured->leftJoins[array_key_first($table)] = $condition;
            return $select;
        });
        $select->method('where')->willReturnCallback(function ($condition) use ($select, $captured) {
            $captured->conditions[] = $condition;
            return $select;
        });
        $select->method('assemble')->willReturn('SELECT 1');

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        $statusAttribute = $this->createMock(Attribute::class);
        $statusAttribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $statusAttribute->method('getAttributeId')->willReturn(97);
        $attributeRepository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $attributeRepository->method('get')->willReturn($statusAttribute);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getById')->with(self::STORE_ID)->willReturn($store);

        $this->stockStatusFilter = $this->createMock(StockStatusFilterInterface::class);

        $this->modifier = new ConfigurableChildStockStatusModifier(
            $metadataPool,
            $resourceConnection,
            $attributeRepository,
            $storeRepository,
            $this->stockStatusFilter
        );
    }

    public function testChildMustBeAssignedToTheWebsiteOfTheStoreView(): void
    {
        $this->modifier->modify($this->createMock(Select::class), self::STORE_ID);

        self::assertArrayHasKey('product_website', $this->existsSelect->joins);
        self::assertSame(
            'product_website.product_id = product_child.entity_id AND product_website.website_id = '
            . self::WEBSITE_ID,
            $this->existsSelect->joins['product_website']
        );
    }

    public function testChildStatusIsStillReadForTheStoreViewWithTheDefaultScopeAsFallback(): void
    {
        $this->modifier->modify($this->createMock(Select::class), self::STORE_ID);

        self::assertStringContainsString(
            'child_status_store.store_id = ' . self::STORE_ID,
            $this->existsSelect->leftJoins['child_status_store']
        );
        self::assertStringContainsString('store_id = 0', $this->existsSelect->leftJoins['child_status_global']);
    }

    public function testStockOfTheWebsiteIsStillFilteredOnTheChild(): void
    {
        $this->stockStatusFilter->expects(self::once())
            ->method('execute')
            ->with(
                self::anything(),
                'product_child',
                StockStatusFilterInterface::TABLE_ALIAS,
                self::WEBSITE_ID
            );

        $this->modifier->modify($this->createMock(Select::class), self::STORE_ID);
    }
}
