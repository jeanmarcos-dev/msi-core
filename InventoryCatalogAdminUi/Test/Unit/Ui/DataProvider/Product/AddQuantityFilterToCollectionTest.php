<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Test\Unit\Ui\DataProvider\Product;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\DB\Select;
use Magento\InventoryCatalogAdminUi\Ui\DataProvider\Product\AddQuantityFilterToCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddQuantityFilterToCollectionTest extends TestCase
{
    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var ProductCollection|MockObject
     */
    private $collection;

    /**
     * @var AddQuantityFilterToCollection
     */
    private $model;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->collection = $this->getMockBuilder(ProductCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSelect'])
            ->getMock();
        $this->collection->method('getSelect')->willReturn($this->select);
        $this->model = new AddQuantityFilterToCollection();
    }

    public function testItFiltersBothEndsOfTheRangeOnTheIndexQuantityColumn(): void
    {
        $this->select->expects(self::exactly(2))->method('where')
            ->willReturnCallback(function (string $condition, $value) {
                self::assertStringStartsWith('at_qty.quantity ', $condition);
                self::assertIsFloat($value);

                return $this->select;
            });

        $this->model->addFilter($this->collection, 'qty', ['gteq' => '5', 'lteq' => '10']);
    }

    public function testItAddsNoConditionWithoutABound(): void
    {
        $this->select->expects(self::never())->method('where');

        $this->model->addFilter($this->collection, 'qty', []);
    }
}
