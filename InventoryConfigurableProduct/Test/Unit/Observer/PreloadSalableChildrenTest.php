<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Observer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use Magento\InventoryConfigurableProduct\Observer\PreloadSalableChildren;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreloadSalableChildrenTest extends TestCase
{
    /**
     * @var SalableChildren|MockObject
     */
    private $salableChildren;

    /**
     * @var PreloadSalableChildren
     */
    private PreloadSalableChildren $observer;

    protected function setUp(): void
    {
        $this->salableChildren = $this->createMock(SalableChildren::class);
        $this->observer = new PreloadSalableChildren($this->salableChildren);
    }

    public function testConfigurableItemsAreResolvedTogetherForTheStoreOfTheCollection(): void
    {
        $first = $this->createItem('configurable');
        $second = $this->createItem('configurable');
        $this->salableChildren->expects(self::once())->method('preload')->with([$first, $second], 3);

        $this->observer->execute($this->createObserver([$first, $this->createItem('simple'), $second], 3));
    }

    public function testCollectionWithoutConfigurableItemsDoesNotQuery(): void
    {
        $this->salableChildren->expects(self::never())->method('preload');

        $this->observer->execute($this->createObserver([$this->createItem('simple')], 3));
    }

    public function testItemsWhoseRowIsZeroAreSkippedBecauseTheRowIsExact(): void
    {
        $unavailable = $this->createItem('configurable', ['is_salable' => '0']);
        $available = $this->createItem('configurable', ['is_salable' => '1']);
        $this->salableChildren->expects(self::once())->method('preload')->with([$available], 3);

        $this->observer->execute($this->createObserver([$unavailable, $available], 3));
    }

    public function testOnlyZeroRowsAreNotResolved(): void
    {
        $this->salableChildren->expects(self::never())->method('preload');

        $this->observer->execute($this->createObserver([$this->createItem('configurable', ['is_salable' => 0])], 3));
    }

    public function testDefaultScopeCollectionIsNotResolved(): void
    {
        $this->salableChildren->expects(self::never())->method('preload');

        $this->observer->execute($this->createObserver([$this->createItem('configurable')], 0));
    }

    /**
     * Build the observer argument carrying a collection with the given items.
     *
     * @param Product[] $items
     * @param int $storeId
     * @return Observer
     */
    private function createObserver(array $items, int $storeId): Observer
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getStoreId')->willReturn($storeId);

        return new Observer(['event' => new Event(['collection' => $collection])]);
    }

    /**
     * Build a product stub of the given type carrying the given data.
     *
     * @param string $typeId
     * @param array $data
     * @return Product|MockObject
     */
    private function createItem(string $typeId, array $data = [])
    {
        $item = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTypeId', 'hasData', 'getData'])
            ->getMock();
        $item->method('getTypeId')->willReturn($typeId);
        $item->method('hasData')->willReturnCallback(fn ($key) => array_key_exists($key, $data));
        $item->method('getData')->willReturnCallback(fn ($key) => $data[$key] ?? null);

        return $item;
    }
}
