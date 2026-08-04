<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Test\Unit\Ui\DataProvider\Product;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\InventoryCatalog\Model\GetStockIndexTableByStoreId;
use Magento\InventoryCatalogAdminUi\Ui\DataProvider\Product\AddQuantityAndStockStatusFieldToCollection;
use Magento\InventoryCatalogAdminUi\Ui\DataProvider\Product\AddQuantityFieldToCollection;
use PHPUnit\Framework\TestCase;

class AddQuantityFieldToCollectionTest extends TestCase
{
    /**
     * @return ProductCollection|\PHPUnit\Framework\MockObject\MockObject
     */
    private function collectionExpecting(string $alias, string $field)
    {
        $collection = $this->getMockBuilder(ProductCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'joinField'])
            ->getMock();
        $collection->method('getStoreId')->willReturn(0);
        $collection->expects(self::once())->method('joinField')
            ->with($alias, 'inventory_stock_1', $field, 'sku=sku', null, 'left');

        return $collection;
    }

    /**
     * @return GetStockIndexTableByStoreId|\PHPUnit\Framework\MockObject\MockObject
     */
    private function tableResolver()
    {
        $resolver = $this->createMock(GetStockIndexTableByStoreId::class);
        $resolver->expects(self::once())->method('execute')->with(0)->willReturn('inventory_stock_1');

        return $resolver;
    }

    public function testItJoinsTheIndexQuantityUnderTheQtyAlias(): void
    {
        $model = new AddQuantityFieldToCollection($this->tableResolver());
        $model->addField($this->collectionExpecting('qty', 'quantity'), 'qty');
    }

    public function testItJoinsTheIndexSalabilityUnderTheStockStatusAlias(): void
    {
        $model = new AddQuantityAndStockStatusFieldToCollection($this->tableResolver());
        $model->addField(
            $this->collectionExpecting('quantity_and_stock_status', 'is_salable'),
            'quantity_and_stock_status'
        );
    }
}
