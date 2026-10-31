<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\InventoryCatalog\Model\Product\PersistedStatusStorage;
use PHPUnit\Framework\TestCase;

class PersistedStatusStorageTest extends TestCase
{
    /**
     * @var PersistedStatusStorage
     */
    private $storage;

    protected function setUp(): void
    {
        $this->storage = new PersistedStatusStorage();
    }

    public function testItReturnsTheStatusStoredForTheProductObject(): void
    {
        $product = $this->createMock(Product::class);
        $this->storage->set($product, 2);

        self::assertSame(2, $this->storage->get($product));
    }

    public function testItKeepsOneStatusPerProductObject(): void
    {
        $first = $this->createMock(Product::class);
        $second = $this->createMock(Product::class);
        $this->storage->set($first, 1);
        $this->storage->set($second, 2);

        self::assertSame(1, $this->storage->get($first));
        self::assertSame(2, $this->storage->get($second));
    }

    public function testItReturnsNullForAProductWithoutAStoredStatus(): void
    {
        self::assertNull($this->storage->get($this->createMock(Product::class)));
    }

    public function testItStoresANullStatus(): void
    {
        $product = $this->createMock(Product::class);
        $this->storage->set($product, 1);
        $this->storage->set($product, null);

        self::assertNull($this->storage->get($product));
    }

    public function testItForgetsTheStatusOfADeletedProduct(): void
    {
        $product = $this->createMock(Product::class);
        $this->storage->set($product, 1);
        $this->storage->delete($product);

        self::assertNull($this->storage->get($product));
    }
}
