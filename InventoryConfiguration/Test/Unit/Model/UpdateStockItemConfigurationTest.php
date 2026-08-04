<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;
use Magento\InventoryConfiguration\Model\UpdateStockItemConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateStockItemConfigurationTest extends TestCase
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
     * @var UpdateStockItemConfiguration
     */
    private $model;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(StockItemConfigurationResource::class);
        $this->cacheStorageMock = $this->createMock(CacheStorage::class);
        $this->model = new UpdateStockItemConfiguration($this->resourceMock, $this->cacheStorageMock);
    }

    public function testNonConfigurationKeysAreDropped(): void
    {
        $this->resourceMock->expects(self::once())
            ->method('updateBySkus')
            ->with(['SKU-1'], ['is_in_stock' => 1]);

        $this->model->execute(['SKU-1'], ['qty' => 10, 'product_id' => 3, 'is_in_stock' => 1]);
    }

    public function testNothingIsWrittenWhenNoConfigurationKeyIsGiven(): void
    {
        $this->resourceMock->expects(self::never())->method('updateBySkus');
        $this->cacheStorageMock->expects(self::never())->method('delete');

        $this->model->execute(['SKU-1'], ['qty' => 10]);
    }

    public function testNothingIsWrittenWithoutSkus(): void
    {
        $this->resourceMock->expects(self::never())->method('updateBySkus');

        $this->model->execute([], ['is_in_stock' => 1]);
    }

    public function testEverySkuIsEvictedFromTheCache(): void
    {
        $evicted = [];
        $this->cacheStorageMock->method('delete')
            ->willReturnCallback(function (string $sku) use (&$evicted): void {
                $evicted[] = $sku;
            });

        $this->model->execute(['SKU-1', 'SKU-2'], ['is_in_stock' => 0]);

        self::assertSame(['SKU-1', 'SKU-2'], $evicted);
    }
}
