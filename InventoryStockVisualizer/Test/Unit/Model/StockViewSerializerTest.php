<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\ChildView;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\Data\SourceView;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see StockViewSerializer
 */
class StockViewSerializerTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var LevelResolver|MockObject
     */
    private $levelResolver;

    /**
     * @var ResolveDisplayConfig|MockObject
     */
    private $resolveDisplayConfig;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->levelResolver = $this->createMock(LevelResolver::class);
        $this->resolveDisplayConfig = $this->createMock(ResolveDisplayConfig::class);
        $this->config->method('getDisplayType')->willReturn(Config::DISPLAY_TYPE_QUANTITY);
    }

    /**
     * @return StockViewSerializer
     */
    private function serializer(): StockViewSerializer
    {
        return new StockViewSerializer($this->config, $this->levelResolver, $this->resolveDisplayConfig);
    }

    /**
     * Aggregate scope emits only the quantity.
     *
     * @return void
     */
    public function testAggregatePayload(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $view = new StockView('SKU-1', 2, 15.0, true, []);

        $this->assertSame(['qty' => 15.0], $this->serializer()->serialize($view));
    }

    /**
     * Per-source scope emits a compact code => qty map with no source metadata.
     *
     * @return void
     */
    public function testPerSourcePayload(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_PER_SOURCE);
        $view = new StockView('SKU-1', 2, 15.0, true, [
            new SourceView('slr_a', 5.0, 'Source A'),
            new SourceView('slr_b', 10.0, 'Source B'),
        ]);

        $this->assertSame(
            ['qty' => 15.0, 'sources' => ['slr_a' => 5.0, 'slr_b' => 10.0]],
            $this->serializer()->serialize($view)
        );
    }

    /**
     * The children fragment carries the aggregate status plus one row per child.
     *
     * @return void
     */
    public function testChildrenPayload(): void
    {
        $view = new StockView('GRP-1', 2, 0.0, true, [], true, true, [
            new ChildView('CH-A', 'Item A', 30.0, true),
            new ChildView('CH-B', 'Item B', 0.0, false),
        ]);

        $this->assertSame(
            [
                'salable' => true,
                'children' => [
                    ['sku' => 'CH-A', 'label' => 'Item A', 'salable' => true, 'qty' => 30.0],
                    ['sku' => 'CH-B', 'label' => 'Item B', 'salable' => false, 'qty' => 0.0],
                ],
            ],
            $this->serializer()->serializeChildren($view)
        );
    }

    /**
     * Level display resolves the quantity to a coarse level server-side and never emits a number.
     *
     * @return void
     */
    public function testLevelPayloadExposesNoQuantity(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getDisplayType')->willReturn(Config::DISPLAY_TYPE_LEVEL);
        $config->method('getScope')->willReturn(Config::SCOPE_PER_SOURCE);
        $displayConfig = $this->displayConfig(Config::DISPLAY_TYPE_LEVEL);
        $this->resolveDisplayConfig->method('forSku')->willReturn($displayConfig);
        $this->levelResolver->method('resolve')->willReturnMap([
            [15.0, $displayConfig, 'high'],
            [5.0, $displayConfig, 'high'],
            [0.0, $displayConfig, 'out'],
        ]);
        $view = new StockView('SKU-1', 2, 15.0, true, [
            new SourceView('slr_a', 5.0, 'Source A'),
            new SourceView('slr_b', 0.0, 'Source B'),
        ]);

        $payload = (new StockViewSerializer($config, $this->levelResolver, $this->resolveDisplayConfig))
            ->serialize($view);

        $this->assertSame(
            ['level' => 'high', 'salable' => true, 'sources' => ['slr_a' => 'high', 'slr_b' => 'out']],
            $payload
        );
        $this->assertArrayNotHasKey('qty', $payload);
    }

    public function testAProductOverriddenToQuantityGetsTheQuantityOnALevelStore(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getDisplayType')->willReturn(Config::DISPLAY_TYPE_LEVEL);
        $config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $this->resolveDisplayConfig->method('forSku')->with('SKU-1')
            ->willReturn($this->displayConfig(Config::DISPLAY_TYPE_QUANTITY));

        $payload = (new StockViewSerializer($config, $this->levelResolver, $this->resolveDisplayConfig))
            ->serialize(new StockView('SKU-1', 2, 15.0, true, []));

        $this->assertSame(['qty' => 15.0], $payload);
    }

    public function testAProductOverriddenToLevelGetsNoQuantityOnAQuantityStore(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $displayConfig = $this->displayConfig(Config::DISPLAY_TYPE_LEVEL);
        $this->resolveDisplayConfig->method('forSku')->with('SKU-1')->willReturn($displayConfig);
        $this->levelResolver->method('resolve')->with(15.0, $displayConfig)->willReturn('high');

        $payload = $this->serializer()->serialize(new StockView('SKU-1', 2, 15.0, true, []));

        $this->assertSame(['level' => 'high', 'salable' => true], $payload);
    }

    public function testARequestedLevelHidesTheQuantityOfAQuantityProduct(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $displayConfig = $this->displayConfig(Config::DISPLAY_TYPE_QUANTITY);
        $this->resolveDisplayConfig->method('forSku')->willReturn($displayConfig);
        $this->levelResolver->method('resolve')->with(15.0, $displayConfig)->willReturn('high');

        $payload = $this->serializer()->serialize(new StockView('SKU-1', 2, 15.0, true, []), true);

        $this->assertSame(['level' => 'high', 'salable' => true], $payload);
    }

    public function testChildrenFollowTheParentOverriddenToQuantityOnALevelStore(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getDisplayType')->willReturn(Config::DISPLAY_TYPE_LEVEL);
        $this->resolveDisplayConfig->method('forSku')->willReturnMap([
            ['GRP-1', null, $this->displayConfig(Config::DISPLAY_TYPE_QUANTITY)],
            ['CH-A', null, $this->displayConfig(Config::DISPLAY_TYPE_LEVEL)],
        ]);
        $view = new StockView('GRP-1', 2, 0.0, true, [], true, true, [
            new ChildView('CH-A', 'Item A', 30.0, true),
        ]);

        $payload = (new StockViewSerializer($config, $this->levelResolver, $this->resolveDisplayConfig))
            ->serializeChildren($view);

        $this->assertSame(
            [
                'salable' => true,
                'children' => [['sku' => 'CH-A', 'label' => 'Item A', 'salable' => true, 'qty' => 30.0]],
            ],
            $payload
        );
    }

    public function testChildrenFollowTheParentOverriddenToLevelOnAQuantityStore(): void
    {
        $childConfig = $this->displayConfig(Config::DISPLAY_TYPE_QUANTITY);
        $this->resolveDisplayConfig->method('forSku')->willReturnMap([
            ['GRP-1', null, $this->displayConfig(Config::DISPLAY_TYPE_LEVEL)],
            ['CH-A', null, $childConfig],
        ]);
        $this->levelResolver->method('resolve')->with(30.0, $childConfig)->willReturn('high');
        $view = new StockView('GRP-1', 2, 0.0, true, [], true, true, [
            new ChildView('CH-A', 'Item A', 30.0, true),
        ]);

        $payload = $this->serializer()->serializeChildren($view);

        $this->assertSame(
            [
                'salable' => true,
                'children' => [['sku' => 'CH-A', 'label' => 'Item A', 'salable' => true, 'level' => 'high']],
            ],
            $payload
        );
    }

    private function displayConfig(string $displayType): DisplayConfig
    {
        return new DisplayConfig($displayType, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
    }
}
