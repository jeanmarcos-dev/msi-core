<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryConfigurableProduct\Model\GetParentIdsWithSalableChildren;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetParentIdsWithSalableChildrenTest extends TestCase
{
    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;

    /**
     * @var SalableProcessor|MockObject
     */
    private $salableProcessor;

    /**
     * @var GetParentIdsWithSalableChildren
     */
    private GetParentIdsWithSalableChildren $getParentIds;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->salableProcessor = $this->createMock(SalableProcessor::class);
        $this->getParentIds = new GetParentIdsWithSalableChildren($this->collectionFactory, $this->salableProcessor);
    }

    public function testNoParentsDoNotQuery(): void
    {
        $this->collectionFactory->expects(self::never())->method('create');

        self::assertSame([], $this->getParentIds->execute([], 1));
    }

    public function testEveryParentIsFilteredForTheStoreAndProcessedAsSalable(): void
    {
        $first = $this->createMock(Product::class);
        $second = $this->createMock(Product::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('setFlag')->with('product_children', true)->willReturnSelf();
        $collection->expects(self::exactly(2))
            ->method('setProductFilter')
            ->willReturnCallback(function ($parent) use ($collection, $first, $second) {
                self::assertContains($parent, [$first, $second]);
                return $collection;
            });
        $collection->expects(self::once())->method('addStoreFilter')->with(6)->willReturnSelf();
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->salableProcessor->expects(self::once())
            ->method('process')
            ->with($collection)
            ->willReturn($collection);
        $select = $this->createMock(Select::class);
        $select->method('reset')->with(Select::COLUMNS)->willReturnSelf();
        $select->expects(self::once())->method('columns')->with('link_table.parent_id')->willReturnSelf();
        $select->expects(self::once())->method('group')->with('link_table.parent_id')->willReturnSelf();
        $collection->method('getSelectCountSql')->willReturn($select);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('fetchCol')->with($select)->willReturn(['3', '9']);
        $collection->method('getConnection')->willReturn($connection);

        self::assertSame([3, 9], $this->getParentIds->execute([$first, $second], 6));
    }
}
