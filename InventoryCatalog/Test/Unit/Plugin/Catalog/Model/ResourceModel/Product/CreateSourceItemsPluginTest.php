<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product\CreateSourceItemsPlugin;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateSourceItemsPluginTest extends TestCase
{
    /**
     * @var GetSourceItemsBySkuInterface|MockObject
     */
    private $getSourceItemsBySku;

    /**
     * @var SourceItemsSaveInterface|MockObject
     */
    private $sourceItemsSave;

    /**
     * @var CreateSourceItemsPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->getSourceItemsBySku = $this->createMock(GetSourceItemsBySkuInterface::class);
        $this->sourceItemsSave = $this->createMock(SourceItemsSaveInterface::class);
        $defaultSourceProvider = $this->createMock(DefaultSourceProviderInterface::class);
        $defaultSourceProvider->method('getCode')->willReturn('default');

        $this->plugin = new CreateSourceItemsPlugin(
            $this->getSourceItemsBySku,
            $this->sourceItemsSave,
            $defaultSourceProvider
        );
    }

    public function testCopiesTheSourceItemsToTheNewSkuThroughTheServiceContract(): void
    {
        $slrA = $this->sourceItem('slr_a');
        $slrB = $this->sourceItem('slr_b');
        $this->getSourceItemsBySku->method('execute')->with('OLD')
            ->willReturn([$slrA, $this->sourceItem('default'), $slrB]);

        $slrA->expects(self::once())->method('setSku')->with('NEW');
        $slrB->expects(self::once())->method('setSku')->with('NEW');
        $this->sourceItemsSave->expects(self::once())->method('execute')->with([0 => $slrA, 2 => $slrB]);

        $this->plugin->afterSave($this->createMock(Product::class), $this->createMock(Product::class), $this->product('OLD', 'NEW'));
    }

    public function testDoesNothingWhenTheSkuDidNotChange(): void
    {
        $this->getSourceItemsBySku->expects(self::never())->method('execute');
        $this->sourceItemsSave->expects(self::never())->method('execute');

        $this->plugin->afterSave($this->createMock(Product::class), $this->createMock(Product::class), $this->product('SAME', 'SAME'));
    }

    public function testDoesNothingWhenOnlyTheDefaultSourceHoldsTheOldSku(): void
    {
        $this->getSourceItemsBySku->method('execute')->willReturn([$this->sourceItem('default')]);

        $this->sourceItemsSave->expects(self::never())->method('execute');

        $this->plugin->afterSave($this->createMock(Product::class), $this->createMock(Product::class), $this->product('OLD', 'NEW'));
    }

    private function sourceItem(string $sourceCode): SourceItemInterface
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->method('getSourceCode')->willReturn($sourceCode);

        return $sourceItem;
    }

    private function product(string $origSku, string $sku): ProductModel
    {
        $product = $this->createMock(ProductModel::class);
        $product->method('getOrigData')->with('sku')->willReturn($origSku);
        $product->method('getSku')->willReturn($sku);

        return $product;
    }
}
