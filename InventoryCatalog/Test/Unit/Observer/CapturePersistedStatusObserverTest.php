<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\InventoryCatalog\Model\Product\PersistedStatusStorage;
use Magento\InventoryCatalog\Model\ResourceModel\GetPersistedProductStatus;
use Magento\InventoryCatalog\Observer\CapturePersistedStatusObserver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CapturePersistedStatusObserverTest extends TestCase
{
    /**
     * @var GetPersistedProductStatus|MockObject
     */
    private $getPersistedProductStatus;

    /**
     * @var PersistedStatusStorage
     */
    private $persistedStatusStorage;

    /**
     * @var CapturePersistedStatusObserver
     */
    private $observer;

    protected function setUp(): void
    {
        $this->getPersistedProductStatus = $this->createMock(GetPersistedProductStatus::class);
        $this->persistedStatusStorage = new PersistedStatusStorage();
        $this->observer = new CapturePersistedStatusObserver(
            $this->getPersistedProductStatus,
            $this->persistedStatusStorage
        );
    }

    public function testItStoresTheStatusPersistedForAnExistingProduct(): void
    {
        $product = $this->createProduct(false);
        $this->getPersistedProductStatus->expects(self::once())
            ->method('execute')
            ->with($product)
            ->willReturn(2);

        $this->observer->execute($this->createEvent($product));

        self::assertSame(2, $this->persistedStatusStorage->get($product));
    }

    public function testItDoesNotQueryTheStatusOfANewProduct(): void
    {
        $product = $this->createProduct(true);
        $this->getPersistedProductStatus->expects(self::never())->method('execute');

        $this->observer->execute($this->createEvent($product));

        self::assertNull($this->persistedStatusStorage->get($product));
    }

    private function createProduct(bool $isNew): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('isObjectNew')->willReturn($isNew);

        return $product;
    }

    private function createEvent(Product $product): Observer
    {
        return new Observer(['event' => new Event(['product' => $product])]);
    }
}
