<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Plugin\GraphQl;

use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use Magento\InventoryConfigurableProduct\Plugin\GraphQl\ConfigurableStockStatusPlugin;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurableStockStatusPluginTest extends TestCase
{
    private const STORE_ID = 5;

    /**
     * @var SalableChildren|MockObject
     */
    private $salableChildren;

    /**
     * @var ConfigurableStockStatusPlugin
     */
    private ConfigurableStockStatusPlugin $plugin;

    protected function setUp(): void
    {
        $this->salableChildren = $this->createMock(SalableChildren::class);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->plugin = new ConfigurableStockStatusPlugin($storeManager, $this->salableChildren);
    }

    public function testConfigurableWithoutSalableChildrenInTheStoreIsOutOfStock(): void
    {
        $product = $this->createProduct('configurable');
        $this->salableChildren->expects(self::once())
            ->method('exist')
            ->with($product, self::STORE_ID)
            ->willReturn(false);

        self::assertSame('OUT_OF_STOCK', $this->afterResolve('IN_STOCK', ['model' => $product]));
    }

    public function testConfigurableWithSalableChildrenInTheStoreStaysInStock(): void
    {
        $this->salableChildren->method('exist')->willReturn(true);

        $value = ['model' => $this->createProduct('configurable')];

        self::assertSame('IN_STOCK', $this->afterResolve('IN_STOCK', $value));
    }

    public function testOutOfStockNeedsNoCheck(): void
    {
        $this->salableChildren->expects(self::never())->method('exist');

        self::assertSame(
            'OUT_OF_STOCK',
            $this->afterResolve('OUT_OF_STOCK', ['model' => $this->createProduct('configurable')])
        );
    }

    public function testOtherProductTypesAreNotChecked(): void
    {
        $this->salableChildren->expects(self::never())->method('exist');

        self::assertSame('IN_STOCK', $this->afterResolve('IN_STOCK', ['model' => $this->createProduct('simple')]));
    }

    public function testValueWithoutProductModelIsLeftAlone(): void
    {
        $this->salableChildren->expects(self::never())->method('exist');

        self::assertSame('IN_STOCK', $this->afterResolve('IN_STOCK', ['sku' => 'x']));
        self::assertSame('IN_STOCK', $this->afterResolve('IN_STOCK', null));
    }

    /**
     * Run the plugin the way the interceptor does.
     *
     * @param string $result
     * @param array|null $value
     * @return mixed
     */
    private function afterResolve(string $result, ?array $value)
    {
        return $this->plugin->afterResolve(
            $this->createMock(ResolverInterface::class),
            $result,
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            $value
        );
    }

    /**
     * Build a product stub of the given type.
     *
     * @param string $typeId
     * @return Product|MockObject
     */
    private function createProduct(string $typeId)
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn($typeId);

        return $product;
    }
}
