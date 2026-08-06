<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Plugin\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;
use Magento\InventoryConfiguration\Plugin\Catalog\Model\ResourceModel\Product\DeleteStockItemConfigurationPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteStockItemConfigurationPluginTest extends TestCase
{
    private const SKU = 'simple1';

    /**
     * @var StockItemConfigurationResource|MockObject
     */
    private $stockItemConfigurationResource;

    /**
     * @var CacheStorage|MockObject
     */
    private $cacheStorage;

    /**
     * @var ProductResource|MockObject
     */
    private $subject;

    /**
     * @var ProductResource|MockObject
     */
    private $result;

    /**
     * @var DeleteStockItemConfigurationPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->stockItemConfigurationResource = $this->createMock(StockItemConfigurationResource::class);
        $this->cacheStorage = $this->createMock(CacheStorage::class);
        $this->subject = $this->createMock(ProductResource::class);
        $this->result = $this->createMock(ProductResource::class);
        $this->plugin = new DeleteStockItemConfigurationPlugin(
            $this->stockItemConfigurationResource,
            $this->cacheStorage
        );
    }

    public function testItDropsTheConfigurationOfTheDeletedProduct(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn(self::SKU);

        $this->stockItemConfigurationResource->expects($this->once())
            ->method('deleteBySkus')
            ->with([self::SKU]);
        $this->cacheStorage->expects($this->once())->method('delete')->with(self::SKU);

        $this->assertSame($this->result, $this->plugin->afterDelete($this->subject, $this->result, $product));
    }

    /**
     * A sku-keyed delete with an empty sku would wipe every row whose sku is an empty string.
     */
    public function testItDoesNothingWithoutASku(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn(null);

        $this->stockItemConfigurationResource->expects($this->never())->method('deleteBySkus');
        $this->cacheStorage->expects($this->never())->method('delete');

        $this->assertSame($this->result, $this->plugin->afterDelete($this->subject, $this->result, $product));
    }
}
