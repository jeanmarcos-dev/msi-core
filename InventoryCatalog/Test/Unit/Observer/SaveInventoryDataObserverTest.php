<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Observer;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\CatalogInventory\Model\StockItemValidator;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\InventoryCatalog\Model\Cache\ProductIdsBySkusStorage;
use Magento\InventoryCatalog\Model\Cache\ProductSkusByIdsStorage;
use Magento\InventoryCatalog\Model\Cache\ProductTypesBySkusStorage;
use Magento\InventoryCatalog\Model\Product\PersistedStatusStorage;
use Magento\InventoryCatalog\Observer\SaveInventoryDataObserver;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveInventoryDataObserverTest extends TestCase
{
    private const SKU = 'child-1';

    /**
     * @var ReindexSourceItemsBySkus|MockObject
     */
    private $reindexSourceItemsBySkus;

    /**
     * @var PersistedStatusStorage
     */
    private $persistedStatusStorage;

    /**
     * @var SaveInventoryDataObserver
     */
    private $observer;

    protected function setUp(): void
    {
        $this->reindexSourceItemsBySkus = $this->createMock(ReindexSourceItemsBySkus::class);
        $this->persistedStatusStorage = new PersistedStatusStorage();
        $this->observer = new SaveInventoryDataObserver(
            $this->createMock(ProductIdsBySkusStorage::class),
            $this->createMock(ProductSkusByIdsStorage::class),
            $this->createMock(ProductTypesBySkusStorage::class),
            $this->reindexSourceItemsBySkus,
            $this->persistedStatusStorage,
            $this->createMock(StockConfigurationInterface::class),
            $this->createMock(StockRegistryInterface::class),
            $this->createMock(StockItemValidator::class)
        );
    }

    public function testItReindexesTheSkuWhenTheStatusDiffersFromThePersistedOne(): void
    {
        $product = $this->createProduct(false, 1);
        $this->persistedStatusStorage->set($product, 2);
        $this->reindexSourceItemsBySkus->expects(self::once())->method('execute')->with([self::SKU]);

        $this->observer->execute($this->createEvent($product));
    }

    public function testItIgnoresTheStatusTheProductWasLoadedWith(): void
    {
        $product = $this->createProduct(false, 1);
        $product->setOrigData('status', 1);
        $this->persistedStatusStorage->set($product, 2);
        $this->reindexSourceItemsBySkus->expects(self::once())->method('execute')->with([self::SKU]);

        $this->observer->execute($this->createEvent($product));
    }

    public function testItDoesNotReindexWhenTheStatusMatchesThePersistedOne(): void
    {
        $product = $this->createProduct(false, 1);
        $this->persistedStatusStorage->set($product, 1);
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $this->observer->execute($this->createEvent($product));
    }

    public function testItDoesNotReindexWhenNoStatusWasPersisted(): void
    {
        $product = $this->createProduct(false, 1);
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $this->observer->execute($this->createEvent($product));
    }

    public function testItDoesNotReindexANewProduct(): void
    {
        $product = $this->createProduct(true, 1);
        $this->persistedStatusStorage->set($product, 2);
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $this->observer->execute($this->createEvent($product));
    }

    public function testItForgetsThePersistedStatusOnceTheProductIsSaved(): void
    {
        $product = $this->createProduct(false, 1);
        $this->persistedStatusStorage->set($product, 2);

        $this->observer->execute($this->createEvent($product));

        self::assertNull($this->persistedStatusStorage->get($product));
    }

    private function createProduct(bool $isNew, int $status): Product
    {
        $stockItem = $this->createMock(Item::class);
        $extensionAttributes = new class ($stockItem) {
            public function __construct(private readonly Item $stockItem)
            {
            }

            public function getStockItem(): Item
            {
                return $this->stockItem;
            }
        };
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isObjectNew', 'getId', 'getSku', 'getStatus', 'getStockData', 'getExtensionAttributes'])
            ->getMock();
        $product->method('isObjectNew')->willReturn($isNew);
        $product->method('getId')->willReturn(10);
        $product->method('getSku')->willReturn(self::SKU);
        $product->method('getStatus')->willReturn($status);
        $product->method('getStockData')->willReturn(null);
        $product->method('getExtensionAttributes')->willReturn($extensionAttributes);

        return $product;
    }

    private function createEvent(Product $product): Observer
    {
        return new Observer(['event' => new Event(['product' => $product])]);
    }
}
