<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\Product;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesAdminUi\Model\AddSourceSalableQuantityBreakdown;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\InventorySalesAdminUi\Ui\DataProvider\Product\Form\Modifier\SalableQuantity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SalableQuantityTest extends TestCase
{
    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface|MockObject
     */
    private $isSourceItemManagementAllowed;

    /**
     * @var GetSalableQuantityDataBySku|MockObject
     */
    private $getSalableQuantityDataBySku;

    /**
     * @var AddSourceSalableQuantityBreakdown|MockObject
     */
    private $addSourceSalableQuantityBreakdown;

    /**
     * @var Product|MockObject
     */
    private $product;

    /**
     * @var SalableQuantity
     */
    private $modifier;

    protected function setUp(): void
    {
        $this->isSourceItemManagementAllowed = $this->createMock(
            IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $this->getSalableQuantityDataBySku = $this->createMock(GetSalableQuantityDataBySku::class);
        $this->addSourceSalableQuantityBreakdown = $this->createMock(AddSourceSalableQuantityBreakdown::class);

        $this->product = $this->createMock(Product::class);
        $this->product->method('getId')->willReturn(42);
        $this->product->method('getSku')->willReturn('sku-1');
        $this->product->method('getTypeId')->willReturn('simple');

        $locator = $this->createMock(LocatorInterface::class);
        $locator->method('getProduct')->willReturn($this->product);

        $this->modifier = new SalableQuantity(
            $this->isSourceItemManagementAllowed,
            $locator,
            $this->getSalableQuantityDataBySku,
            $this->addSourceSalableQuantityBreakdown
        );
    }

    public function testReportsTheSalableQuantityBrokenDownBySource(): void
    {
        $this->isSourceItemManagementAllowed->method('execute')->willReturn(true);
        $stockEntries = [['stock_id' => 2, 'stock_name' => 'EU Stock', 'qty' => 15.0, 'manage_stock' => true]];
        $brokenDown = [['stock_id' => 2, 'stock_name' => 'EU Stock', 'qty' => 15.0, 'manage_stock' => true,
            'sources' => [['source_code' => 'src_a', 'salable' => 3.0]], 'source_reservations_enabled' => true]];

        $this->getSalableQuantityDataBySku->method('execute')->with('sku-1')->willReturn($stockEntries);
        $this->addSourceSalableQuantityBreakdown->expects(self::once())
            ->method('execute')
            ->with(['sku-1' => $stockEntries])
            ->willReturn(['sku-1' => $brokenDown]);

        self::assertSame($brokenDown, $this->modifier->modifyData([])[42]['salable_quantity']);
    }

    public function testLeavesTheDataUntouchedForATypeWithoutSourceItemManagement(): void
    {
        $this->isSourceItemManagementAllowed->method('execute')->willReturn(false);
        $this->getSalableQuantityDataBySku->expects(self::never())->method('execute');
        $this->addSourceSalableQuantityBreakdown->expects(self::never())->method('execute');

        self::assertSame([], $this->modifier->modifyData([]));
    }

    public function testLeavesTheMetaUntouchedForATypeWithoutSourceItemManagement(): void
    {
        $this->isSourceItemManagementAllowed->method('execute')->willReturn(false);

        self::assertSame([], $this->modifier->modifyMeta([]));
    }

    public function testMakesTheFieldsetVisibleForAStockableProduct(): void
    {
        $this->isSourceItemManagementAllowed->method('execute')->willReturn(true);

        $meta = $this->modifier->modifyMeta([]);

        self::assertSame(1, $meta['salable_quantity']['arguments']['data']['config']['visible']);
    }
}
