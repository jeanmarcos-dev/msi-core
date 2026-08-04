<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\InventoryConfiguration\Model\ProjectLegacyStockItemToConfiguration;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProjectLegacyStockItemToConfigurationTest extends TestCase
{
    /**
     * @var StockItemConfigurationResource|MockObject
     */
    private $resourceMock;

    /**
     * @var CacheStorage|MockObject
     */
    private $cacheStorageMock;

    /**
     * @var ProjectLegacyStockItemToConfiguration
     */
    private $model;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(StockItemConfigurationResource::class);
        $this->cacheStorageMock = $this->createMock(CacheStorage::class);
        $this->model = new ProjectLegacyStockItemToConfiguration($this->resourceMock, $this->cacheStorageMock);
    }

    public function testOnlyConfigurationColumnsPresentOnTheEntityAreProjected(): void
    {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn([
            'item_id' => 7,
            'product_id' => 3,
            'stock_id' => 1,
            'qty' => 12.0,
            'manage_stock' => 1,
            'is_in_stock' => 0,
        ]);

        $this->resourceMock->expects(self::once())
            ->method('save')
            ->with([['sku' => 'SKU-1', 'is_in_stock' => 0, 'manage_stock' => 1]]);

        $this->model->execute('SKU-1', $legacyStockItem);
    }

    public function testAbsentColumnsAreOmittedRatherThanNulled(): void
    {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn(['min_qty' => 4]);

        $this->resourceMock->expects(self::once())
            ->method('save')
            ->with([['sku' => 'SKU-1', 'min_qty' => 4]]);

        $this->model->execute('SKU-1', $legacyStockItem);
    }

    public function testCacheIsEvicted(): void
    {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn(['manage_stock' => 1]);

        $this->cacheStorageMock->expects(self::once())->method('delete')->with('SKU-1');

        $this->model->execute('SKU-1', $legacyStockItem);
    }
}
