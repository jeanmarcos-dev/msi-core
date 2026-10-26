<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Block\Product\AvailabilityData;
use Magento\InventoryStockVisualizer\Block\Product\StockVisualizer;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see StockVisualizer
 */
class StockVisualizerTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const PRODUCT_ID = 42;
    private const STOCK_ID = 10;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var AvailabilityData|MockObject
     */
    private $availabilityData;

    /**
     * @var ProductInterface|MockObject
     */
    private $product;

    /**
     * @var StockViewInterface|MockObject
     */
    private $view;

    /**
     * @var StockVisualizer
     */
    private $block;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->availabilityData = $this->createMock(AvailabilityData::class);
        $this->product = $this->createMock(ProductInterface::class);
        $this->view = $this->createMock(StockViewInterface::class);

        $this->product->method('getSku')->willReturn(self::SKU);
        $this->product->method('getId')->willReturn(self::PRODUCT_ID);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMode')->willReturn(Config::MODE_ON_DEMAND);
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($this->product);
        $this->availabilityData->method('resolveStockId')->willReturn(self::STOCK_ID);
        $this->availabilityData->method('view')->willReturn($this->view);

        $this->block = (new ObjectManager($this))->getObject(
            StockVisualizer::class,
            [
                'registry' => $registry,
                'config' => $this->config,
                'json' => new Json(),
                'availabilityData' => $this->availabilityData,
            ]
        );
    }

    /**
     * An out-of-stock product needs no client component: the fetch could only confirm a known zero.
     *
     * @param string $typeId
     * @param string $compositeMode
     * @return void
     */
    #[DataProvider('productTypeProvider')]
    public function testComponentSuppressedWhenOutOfStock(string $typeId, string $compositeMode): void
    {
        $this->givenProduct($typeId, $compositeMode, false);

        $this->assertTrue($this->block->isOutOfStock());
        $this->assertSame(StockVisualizer::KIND_NONE, $this->block->getComponentKind());
    }

    /**
     * A salable product keeps its component; the guard must not regress the widget.
     *
     * @param string $typeId
     * @param string $compositeMode
     * @param string $expectedKind
     * @return void
     */
    #[DataProvider('componentKindProvider')]
    public function testComponentKindPreservedWhenSalable(
        string $typeId,
        string $compositeMode,
        string $expectedKind
    ): void {
        $this->givenProduct($typeId, $compositeMode, true);

        $this->assertFalse($this->block->isOutOfStock());
        $this->assertSame($expectedKind, $this->block->getComponentKind());
    }

    /**
     * Product type, configured composite mode and the strategy it resolves to.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function componentKindProvider(): array
    {
        return [
            'simple' => ['simple', '', StockVisualizer::KIND_QUANTITY],
            'configurable variant' => [
                'configurable',
                Config::COMPOSITE_MODE_VARIANT,
                StockVisualizer::KIND_VARIANT,
            ],
            'grouped children' => ['grouped', Config::COMPOSITE_MODE_CHILDREN, StockVisualizer::KIND_CHILDREN],
            'bundle max' => ['bundle', Config::COMPOSITE_MODE_MAX, StockVisualizer::KIND_BUNDLE_MAX],
        ];
    }

    public static function productTypeProvider(): array
    {
        return array_map(
            static fn(array $row): array => [$row[0], $row[1]],
            self::componentKindProvider()
        );
    }

    /**
     * Without a component there is no mount payload, so nothing schedules a fetch.
     *
     * @return void
     */
    public function testInitJsonIsEmptyWhenOutOfStock(): void
    {
        $this->givenProduct('simple', '', false);

        $this->assertSame('', $this->block->getInitJson());
    }

    /**
     * Level mode is fully server-rendered and has no component to suppress, so the guard is inert.
     *
     * @return void
     */
    public function testLevelModeIsUnaffectedByOutOfStock(): void
    {
        $this->givenProduct('simple', '', false, Config::DISPLAY_TYPE_LEVEL);

        $this->assertFalse($this->block->isOutOfStock());
        $this->assertSame(StockVisualizer::KIND_NONE, $this->block->getComponentKind());
    }

    /**
     * Seed the collaborators for a product of the given type and salability.
     *
     * @param string $typeId
     * @param string $compositeMode
     * @param bool $salable
     * @param string $displayType
     * @return void
     */
    private function givenProduct(
        string $typeId,
        string $compositeMode,
        bool $salable,
        string $displayType = Config::DISPLAY_TYPE_QUANTITY
    ): void {
        $this->product->method('getTypeId')->willReturn($typeId);
        $this->config->method('getConfigurableMode')->willReturn($compositeMode);
        $this->config->method('getBundleMode')->willReturn($compositeMode);
        $this->config->method('getGroupedMode')->willReturn($compositeMode);
        $this->view->method('isSalable')->willReturn($salable);
        $this->view->method('isAggregateOnly')->willReturn($typeId !== 'simple');
        $this->availabilityData->method('displayConfig')->willReturn(
            new DisplayConfig($displayType, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null)
        );
    }
}
