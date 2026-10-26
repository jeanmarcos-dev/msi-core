<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Cache\GetEnabledStoreIds;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveSkusToPurge;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\SourceView;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\Product\StockVisualizerAttributes;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see ResolveSkusToPurge
 */
class ResolveSkusToPurgeTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const STOCK_ID = 10;

    /**
     * @var GetEnabledStoreIds|MockObject
     */
    private $enabledStores;

    /**
     * @var ResolveDisplayConfig|MockObject
     */
    private $resolveDisplayConfig;

    /**
     * @var GetStockViewInterface|MockObject
     */
    private $getStockView;

    /**
     * @var ResolveSkusToPurge
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enabledStores = $this->createMock(GetEnabledStoreIds::class);
        $this->resolveDisplayConfig = $this->createMock(ResolveDisplayConfig::class);
        $this->getStockView = $this->createMock(GetStockViewInterface::class);

        $this->model = new ResolveSkusToPurge(
            $this->enabledStores,
            $this->resolveDisplayConfig,
            new LevelResolver(),
            $this->getStockView
        );
    }

    /**
     * Disabled feature resolves nothing.
     *
     * @return void
     */
    public function testDisabledResolvesNothing(): void
    {
        $this->enabledStores->method('execute')->willReturn([]);
        $this->resolveDisplayConfig->expects($this->never())->method('forSku');

        $this->assertSame([], $this->model->execute($this->deltas(-2.0)));
    }

    /**
     * Quantity display resolves every touched SKU.
     *
     * @return void
     */
    public function testQuantityModeResolvesTouchedSku(): void
    {
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->quantityConfig());

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-2.0)));
    }

    /**
     * Level display skips a SKU whose level did not change.
     *
     * @return void
     */
    public function testLevelModeSkipsWhenLevelUnchanged(): void
    {
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 8.0, true, []));

        $this->assertSame([], $this->model->execute($this->deltas(-2.0)));
    }

    /**
     * Level display resolves a SKU whose aggregate level crossed a threshold.
     *
     * @return void
     */
    public function testLevelModeResolvesWhenAggregateLevelChanges(): void
    {
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 2.0, true, []));

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-5.0)));
    }

    /**
     * Level display resolves a SKU whose per-source level crossed even if the aggregate stays put.
     *
     * @return void
     */
    public function testLevelModeResolvesWhenPerSourceLevelChanges(): void
    {
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $view = new StockView(self::SKU, self::STOCK_ID, 8.0, true, [new SourceView('slr_a', 3.0, 'A')]);
        $this->getStockView->method('execute')->willReturn($view);

        $this->assertSame([self::SKU], $this->model->execute($this->sourceDeltas(-1.0, 'slr_a')));
    }

    public function testAPanelEnabledOnlyOnAStoreViewIsJudgedWithThatStoreViewConfiguration(): void
    {
        $this->enabledStores->method('execute')->willReturn([2]);
        $this->resolveDisplayConfig->expects($this->once())->method('forSku')
            ->with(self::SKU, 2)
            ->willReturn($this->quantityConfig());

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-1.0)));
    }

    public function testAStoreViewShowingTheQuantityPurgesAChangeTheDefaultLevelHides(): void
    {
        $this->enabledStores->method('execute')->willReturn([1, 2]);
        $this->resolveDisplayConfig->method('forSku')->willReturnCallback(
            fn(string $sku, $store = null) => $store === 2 ? $this->quantityConfig() : $this->levelConfig()
        );
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 24.0, true, []));

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-1.0)));
    }

    public function testAStoreViewWithItsOwnThresholdsPurgesTheLevelItCrosses(): void
    {
        $this->enabledStores->method('execute')->willReturn([1, 2]);
        $this->resolveDisplayConfig->method('forSku')->willReturnCallback(
            fn(string $sku, $store = null) => $store === 2 ? $this->levelConfig(30.0) : $this->levelConfig()
        );
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 29.0, true, []));

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-2.0)));
    }

    public function testNothingIsPurgedWhenNoStoreViewWouldShowADifference(): void
    {
        $this->enabledStores->method('execute')->willReturn([1, 2]);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 24.0, true, []));

        $this->assertSame([], $this->model->execute($this->deltas(-1.0)));
    }

    public function testTheProductOverrideWinsOverAStoreViewShowingTheQuantity(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getDisplayType')->willReturn(Config::DISPLAY_TYPE_QUANTITY);
        $config->method('getLevelBasis')->willReturn(Config::LEVEL_BASIS_QUANTITY);
        $config->method('getLevelHigh')->willReturn(10.0);
        $config->method('getLevelLow')->willReturn(3.0);
        $override = $this->createStub(AttributeInterface::class);
        $override->method('getValue')->willReturn(Config::DISPLAY_TYPE_LEVEL);
        $product = $this->createStub(ProductInterface::class);
        $product->method('getCustomAttribute')->willReturnCallback(
            fn(string $code) => $code === StockVisualizerAttributes::DISPLAY_TYPE ? $override : null
        );
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('get')->willReturn($product);
        $this->enabledStores->method('execute')->willReturn([2]);
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 24.0, true, []));
        $model = new ResolveSkusToPurge(
            $this->enabledStores,
            new ResolveDisplayConfig($config, $productRepository),
            new LevelResolver(),
            $this->getStockView
        );

        $this->assertSame([], $model->execute($this->deltas(-1.0)));
    }

    public function testStoreViewsWithTheSameConfigurationReadTheStockOnce(): void
    {
        $this->enabledStores->method('execute')->willReturn([1, 2, 3]);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->expects($this->once())->method('execute')
            ->willReturn(new StockView(self::SKU, self::STOCK_ID, 24.0, true, []));

        $this->model->execute($this->deltas(-1.0));
    }

    public function testAStoreViewShowingTheQuantityDoesNotReadTheStock(): void
    {
        $this->enabledStores->method('execute')->willReturn([1, 2]);
        $this->resolveDisplayConfig->method('forSku')->willReturnCallback(
            fn(string $sku, $store = null) => $store === 2 ? $this->quantityConfig() : $this->levelConfig()
        );
        $this->getStockView->expects($this->never())->method('execute');

        $this->model->execute($this->deltas(-1.0));
    }

    /**
     * @param float $total
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    private function deltas(float $total): array
    {
        return [self::STOCK_ID => [self::SKU => ['total' => $total, 'bySource' => []]]];
    }

    /**
     * @param float $total
     * @param string $sourceCode
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    private function sourceDeltas(float $total, string $sourceCode): array
    {
        return [self::STOCK_ID => [self::SKU => ['total' => $total, 'bySource' => [$sourceCode => $total]]]];
    }

    /**
     * @param float $high
     * @return DisplayConfig
     */
    private function levelConfig(float $high = 10.0): DisplayConfig
    {
        return new DisplayConfig(Config::DISPLAY_TYPE_LEVEL, Config::LEVEL_BASIS_QUANTITY, $high, 3.0, null);
    }

    /**
     * @return DisplayConfig
     */
    private function quantityConfig(): DisplayConfig
    {
        return new DisplayConfig(Config::DISPLAY_TYPE_QUANTITY, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
    }
}
