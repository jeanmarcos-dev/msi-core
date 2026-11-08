<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Plugin\Model\ResourceModel\Attribute;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Attribute\OptionSelectBuilderInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryConfigurableProduct\Plugin\Model\ResourceModel\Attribute\IsSalableOptionSelectBuilder;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsSalableOptionSelectBuilderTest extends TestCase
{
    public static function stockIds(): array
    {
        return [
            'stock 1' => [1],
            'a merchant stock' => [5],
        ];
    }

    #[DataProvider('stockIds')]
    public function testKeepsOnlyTheOptionsSalableInTheStockOfTheWebsite(int $stockId): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('joinInner')
            ->with(['stock' => 'inventory_stock_' . $stockId], 'stock.sku = entity.sku', [])
            ->willReturnSelf();
        $select->expects($this->once())
            ->method('where')
            ->with('stock.is_salable = ?', 1)
            ->willReturnSelf();

        $this->assertSame($select, $this->afterGetSelect($stockId, false, $select));
    }

    #[DataProvider('stockIds')]
    public function testKeepsEveryOptionWhenOutOfStockProductsAreDisplayed(int $stockId): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->never())->method('joinInner');
        $select->expects($this->never())->method('where');

        $this->assertSame($select, $this->afterGetSelect($stockId, true, $select));
    }

    private function afterGetSelect(int $stockId, bool $showOutOfStock, Select $select): Select
    {
        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getCode')->willReturn('dss_web');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturn($website);

        $stock = $this->createStub(StockInterface::class);
        $stock->method('getStockId')->willReturn($stockId);
        $stockResolver = $this->createMock(StockResolverInterface::class);
        $stockResolver->method('execute')
            ->with(SalesChannelInterface::TYPE_WEBSITE, 'dss_web')
            ->willReturn($stock);

        $tableNameResolver = $this->createStub(StockIndexTableNameResolverInterface::class);
        $tableNameResolver->method('execute')->willReturnCallback(
            static fn (int $id): string => 'inventory_stock_' . $id
        );

        $stockConfig = $this->createStub(StockConfigurationInterface::class);
        $stockConfig->method('isShowOutOfStock')->willReturn($showOutOfStock);

        $plugin = new IsSalableOptionSelectBuilder($storeManager, $stockResolver, $tableNameResolver, $stockConfig);

        return $plugin->afterGetSelect($this->createStub(OptionSelectBuilderInterface::class), $select);
    }
}
