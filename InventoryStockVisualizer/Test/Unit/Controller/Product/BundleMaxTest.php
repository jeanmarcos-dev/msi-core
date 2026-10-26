<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Controller\Product;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryStockVisualizer\Controller\FragmentResponder;
use Magento\InventoryStockVisualizer\Controller\Product\BundleMax;
use Magento\InventoryStockVisualizer\Model\Availability\BundleMaxResult;
use Magento\InventoryStockVisualizer\Model\Availability\GetBundleMaxSellable;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use PHPUnit\Framework\TestCase;

/**
 * @see BundleMax
 */
class BundleMaxTest extends TestCase
{
    private const SKU = 'BND-1';

    /**
     * @var mixed
     */
    private $data;

    public function testABundleOverriddenToQuantityGetsTheCountOnALevelStore(): void
    {
        $this->assertSame(['max' => 2], $this->payload(Config::DISPLAY_TYPE_LEVEL, Config::DISPLAY_TYPE_QUANTITY));
    }

    public function testABundleOverriddenToLevelGetsNoCountOnAQuantityStore(): void
    {
        $this->assertSame(
            ['level' => 'low', 'salable' => true],
            $this->payload(Config::DISPLAY_TYPE_QUANTITY, Config::DISPLAY_TYPE_LEVEL)
        );
    }

    private function payload(string $storeDisplayType, string $bundleDisplayType): array
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getDisplayType')->willReturn($storeDisplayType);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn (string $key) => $key === 'sku' ? self::SKU : null);
        $stockId = $this->createStub(GetStockIdForCurrentWebsite::class);
        $stockId->method('execute')->willReturn(2);
        $getBundleMax = $this->createStub(GetBundleMaxSellable::class);
        $getBundleMax->method('execute')->willReturn(new BundleMaxResult(2, [11, 12]));
        $displayConfig = new DisplayConfig($bundleDisplayType, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
        $resolveDisplayConfig = $this->createStub(ResolveDisplayConfig::class);
        $resolveDisplayConfig->method('forSku')->willReturn($displayConfig);
        $levelResolver = $this->createStub(LevelResolver::class);
        $levelResolver->method('resolve')->willReturn('low');

        $controller = new BundleMax(
            $request,
            $config,
            $stockId,
            $getBundleMax,
            $levelResolver,
            $resolveDisplayConfig,
            $this->responder($config)
        );
        $controller->execute();

        return $this->data['data'];
    }

    private function responder(Config $config): FragmentResponder
    {
        $result = $this->createStub(Json::class);
        $result->method('setHeader')->willReturn($result);
        $result->method('setData')->willReturnCallback(function ($data) use ($result): Json {
            $this->data = $data;

            return $result;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($result);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('120');

        return new FragmentResponder(
            $jsonFactory,
            $scopeConfig,
            $config,
            $this->createStub(GetProductIdsBySkusInterface::class),
            $this->createStub(GetSkusByProductIdsInterface::class)
        );
    }
}
