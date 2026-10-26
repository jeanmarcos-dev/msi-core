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
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Controller\FragmentResponder;
use Magento\InventoryStockVisualizer\Controller\Product\Children;
use Magento\InventoryStockVisualizer\Model\Availability\GetGroupedSetsMax;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @see Children
 */
class ChildrenTest extends TestCase
{
    private const SKU = 'GRP-1';

    /**
     * @var mixed
     */
    private $data;

    public function testTheSetCountFollowsAParentOverriddenToQuantityOnALevelStore(): void
    {
        $this->assertSame(3, $this->sets(Config::DISPLAY_TYPE_LEVEL, Config::DISPLAY_TYPE_QUANTITY));
    }

    public function testTheSetCountCollapsesForAParentOverriddenToLevelOnAQuantityStore(): void
    {
        $this->assertSame(1, $this->sets(Config::DISPLAY_TYPE_QUANTITY, Config::DISPLAY_TYPE_LEVEL));
    }

    private function sets(string $storeDisplayType, string $parentDisplayType): int
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isGroupedSetsCalculatorEnabled')->willReturn(true);
        $config->method('getGroupedMode')->willReturn(Config::COMPOSITE_MODE_CHILDREN);
        $config->method('getDisplayType')->willReturn($storeDisplayType);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn (string $key) => $key === 'sku' ? self::SKU : null);
        $stockId = $this->createStub(GetStockIdForCurrentWebsite::class);
        $stockId->method('execute')->willReturn(2);
        $getStockView = $this->createStub(GetStockViewInterface::class);
        $getStockView->method('execute')->willReturn(new StockView(self::SKU, 2, 0.0, true, [], true, true, []));
        $serializer = $this->createStub(StockViewSerializer::class);
        $serializer->method('serializeChildren')->willReturn(['salable' => true, 'children' => []]);
        $setsMax = $this->createStub(GetGroupedSetsMax::class);
        $setsMax->method('execute')->willReturn(3);
        $resolveDisplayConfig = $this->createStub(ResolveDisplayConfig::class);
        $resolveDisplayConfig->method('forSku')->willReturnCallback(
            fn (string $sku) => new DisplayConfig(
                $sku === self::SKU ? $parentDisplayType : $storeDisplayType,
                Config::LEVEL_BASIS_QUANTITY,
                10.0,
                3.0,
                null
            )
        );

        $controller = new Children(
            $request,
            $config,
            $getStockView,
            $serializer,
            $stockId,
            $setsMax,
            $this->responder($config),
            $resolveDisplayConfig
        );
        $controller->execute();

        return $this->data['data']['sets'];
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

        return new FragmentResponder(
            $jsonFactory,
            $this->createStub(ScopeConfigInterface::class),
            $config,
            $this->createStub(GetProductIdsBySkusInterface::class),
            $this->createStub(GetSkusByProductIdsInterface::class)
        );
    }
}
