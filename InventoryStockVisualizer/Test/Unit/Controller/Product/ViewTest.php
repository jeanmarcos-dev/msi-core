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
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Controller\FragmentResponder;
use Magento\InventoryStockVisualizer\Controller\Product\View;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;
use Magento\PageCache\Model\Config as PageCacheConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see View
 */
class ViewTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const PRODUCT_ID = 42;
    private const STOCK_ID = 10;

    /**
     * @var RequestInterface|MockObject
     */
    private $request;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var GetStockViewInterface|MockObject
     */
    private $getStockView;

    /**
     * @var StockViewSerializer|MockObject
     */
    private $serializer;

    /**
     * @var GetStockIdForCurrentWebsite|MockObject
     */
    private $getStockId;

    /**
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkus;

    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $getSkusByProductIds;

    /**
     * @var ResolveDisplayConfig|MockObject
     */
    private $resolveDisplayConfig;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var Json|MockObject
     */
    private $result;

    /**
     * @var array<string, string>
     */
    private $headers = [];

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var View
     */
    private $controller;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->createMock(RequestInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->getStockView = $this->createMock(GetStockViewInterface::class);
        $this->serializer = $this->createMock(StockViewSerializer::class);
        $this->getStockId = $this->createMock(GetStockIdForCurrentWebsite::class);
        $this->getProductIdsBySkus = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->resolveDisplayConfig = $this->createMock(ResolveDisplayConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);

        $this->result = $this->createMock(Json::class);
        $this->result->method('setHeader')->willReturnCallback(
            function (string $name, string $value): Json {
                $this->headers[$name] = $value;

                return $this->result;
            }
        );
        $this->result->method('setData')->willReturnCallback(
            function ($data): Json {
                $this->data = $data;

                return $this->result;
            }
        );

        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($this->result);
        $pageCacheConfig = $this->createStub(PageCacheConfig::class);
        $pageCacheConfig->method('isEnabled')->willReturn(true);

        $responder = new FragmentResponder(
            $jsonFactory,
            $this->scopeConfig,
            $this->config,
            $this->getProductIdsBySkus,
            $this->getSkusByProductIds,
            $pageCacheConfig
        );

        $this->controller = new View(
            $this->request,
            $this->config,
            $this->getStockView,
            $this->serializer,
            $this->getStockId,
            $responder
        );
    }

    /**
     * The disabled feature returns a non-cacheable null payload.
     *
     * @return void
     */
    public function testDisabledReturnsUncacheableNull(): void
    {
        $this->request->method('getParam')->willReturn(self::SKU);
        $this->config->method('isEnabled')->willReturn(false);

        $this->controller->execute();

        $this->assertSame(['data' => null], $this->data);
        $this->assertStringContainsString('no-store', $this->headers['Cache-Control']);
        $this->assertArrayNotHasKey('X-Magento-Tags', $this->headers);
    }

    /**
     * A blank SKU short-circuits without touching the inventory services.
     *
     * @return void
     */
    public function testBlankSkuReturnsUncacheableNull(): void
    {
        $this->request->method('getParam')->willReturn('');
        $this->config->method('isEnabled')->willReturn(true);
        $this->getStockView->expects($this->never())->method('execute');

        $this->controller->execute();

        $this->assertSame(['data' => null], $this->data);
        $this->assertArrayNotHasKey('X-Magento-Tags', $this->headers);
    }

    /**
     * Quantity mode emits the payload, the SKU-resolved purge tag and public cache headers.
     *
     * @return void
     */
    public function testQuantityModeEmitsCacheableTaggedPayload(): void
    {
        $this->request->method('getParam')->willReturn(self::SKU);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getTtl')->willReturn(0);
        $this->scopeConfig->method('getValue')->willReturn('120');
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->displayConfig(Config::DISPLAY_TYPE_QUANTITY));
        $this->getStockId->method('execute')->willReturn(self::STOCK_ID);
        $this->getStockView->method('execute')->willReturn($this->createMock(StockViewInterface::class));
        $this->serializer->method('serialize')->willReturn(['qty' => 15.0]);
        $this->getProductIdsBySkus->method('execute')->willReturn([self::SKU => self::PRODUCT_ID]);

        $this->controller->execute();

        $this->assertSame(['data' => ['qty' => 15.0]], $this->data);
        $this->assertSame(
            'inv_stockviz_' . self::PRODUCT_ID . ',cat_p_' . self::PRODUCT_ID,
            $this->headers['X-Magento-Tags']
        );
        $this->assertStringContainsString('public', $this->headers['Cache-Control']);
        $this->assertStringContainsString('max-age=120', $this->headers['Cache-Control']);
    }

    /**
     * An unresolvable SKU stays non-cacheable so no fragment is tagged under a wrong id.
     *
     * @return void
     */
    public function testUnresolvableProductIdIsUncacheable(): void
    {
        $this->request->method('getParam')->willReturn(self::SKU);
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->displayConfig(Config::DISPLAY_TYPE_QUANTITY));
        $this->getStockId->method('execute')->willReturn(self::STOCK_ID);
        $this->getStockView->method('execute')->willReturn($this->createMock(StockViewInterface::class));
        $this->serializer->method('serialize')->willReturn(['qty' => 15.0]);
        $this->getProductIdsBySkus->method('execute')->willReturn([]);

        $this->controller->execute();

        $this->assertSame(['data' => null], $this->data);
        $this->assertArrayNotHasKey('X-Magento-Tags', $this->headers);
    }

    /**
     * A failure while computing availability degrades to a non-cacheable null payload.
     *
     * @return void
     */
    public function testThrowableDegradesToUncacheableNull(): void
    {
        $this->request->method('getParam')->willReturn(self::SKU);
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->displayConfig(Config::DISPLAY_TYPE_QUANTITY));
        $this->getStockId->method('execute')->willReturn(self::STOCK_ID);
        $this->getStockView->method('execute')->willThrowException(new \RuntimeException('boom'));

        $this->controller->execute();

        $this->assertSame(['data' => null], $this->data);
        $this->assertArrayNotHasKey('X-Magento-Tags', $this->headers);
    }

    public function testAVariantShownAsALevelAsksTheSerializerForALevel(): void
    {
        $params = ['sku' => '', 'product_id' => '7', 'level' => '1'];
        $this->request->method('getParam')->willReturnCallback(fn (string $key) => $params[$key] ?? null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->getSkusByProductIds->method('execute')->with([7])->willReturn([7 => 'CH-1']);
        $this->getStockId->method('execute')->willReturn(self::STOCK_ID);
        $view = $this->createStub(StockViewInterface::class);
        $this->getStockView->method('execute')->with('CH-1', self::STOCK_ID)->willReturn($view);
        $this->getProductIdsBySkus->method('execute')->willReturn(['CH-1' => 7]);

        $this->serializer->expects($this->once())->method('serialize')
            ->with($view, true)
            ->willReturn(['level' => 'high']);

        $this->controller->execute();

        $this->assertSame(['data' => ['level' => 'high']], $this->data);
    }

    public function testAProductPageAsksForItsOwnDisplay(): void
    {
        $params = ['sku' => self::SKU];
        $this->request->method('getParam')->willReturnCallback(fn (string $key) => $params[$key] ?? null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->getStockId->method('execute')->willReturn(self::STOCK_ID);
        $view = $this->createStub(StockViewInterface::class);
        $this->getStockView->method('execute')->willReturn($view);
        $this->getProductIdsBySkus->method('execute')->willReturn([self::SKU => self::PRODUCT_ID]);

        $this->serializer->expects($this->once())->method('serialize')
            ->with($view, false)
            ->willReturn(['qty' => 1.0]);

        $this->controller->execute();
    }

    /**
     * Build a display config with the given display type.
     *
     * @param string $displayType
     * @return DisplayConfig
     */
    private function displayConfig(string $displayType): DisplayConfig
    {
        return new DisplayConfig($displayType, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
    }
}
