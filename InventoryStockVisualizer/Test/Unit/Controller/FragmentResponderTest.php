<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Controller;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryStockVisualizer\Controller\FragmentResponder;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\PageCache\Model\Config as PageCacheConfig;
use PHPUnit\Framework\TestCase;

/**
 * @see FragmentResponder
 */
class FragmentResponderTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * @var mixed
     */
    private $data;

    public function testAFragmentCarriesTheProductTagOfEveryProductItDescribes(): void
    {
        $this->responder(true)->cacheable($this->jsonResult(), [7, 9], ['qty' => 1.0]);

        $this->assertSame(
            ['inv_stockviz_7', 'cat_p_7', 'inv_stockviz_9', 'cat_p_9'],
            explode(',', $this->headers['X-Magento-Tags'])
        );
    }

    public function testAFragmentIsPublicWhileThePageCacheIsOn(): void
    {
        $this->responder(true)->cacheable($this->jsonResult(), [7], ['qty' => 1.0]);

        $this->assertSame('public, max-age=120, s-maxage=120', $this->headers['Cache-Control']);
    }

    public function testAFragmentIsNotStoredWhileThePageCacheIsOff(): void
    {
        $this->responder(false)->cacheable($this->jsonResult(), [7], ['qty' => 1.0]);

        $this->assertStringContainsString('no-store', $this->headers['Cache-Control']);
        $this->assertArrayNotHasKey('X-Magento-Tags', $this->headers);
        $this->assertSame(['data' => ['qty' => 1.0]], $this->data);
    }

    private function responder(bool $pageCacheEnabled): FragmentResponder
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('120');
        $pageCacheConfig = $this->createStub(PageCacheConfig::class);
        $pageCacheConfig->method('isEnabled')->willReturn($pageCacheEnabled);

        return new FragmentResponder(
            $this->createStub(JsonFactory::class),
            $scopeConfig,
            $this->createStub(Config::class),
            $this->createStub(GetProductIdsBySkusInterface::class),
            $this->createStub(GetSkusByProductIdsInterface::class),
            $pageCacheConfig
        );
    }

    private function jsonResult(): Json
    {
        $result = $this->createStub(Json::class);
        $result->method('setHeader')->willReturnCallback(function (string $name, string $value) use ($result): Json {
            $this->headers[$name] = $value;

            return $result;
        });
        $result->method('setData')->willReturnCallback(function ($data) use ($result): Json {
            $this->data = $data;

            return $result;
        });

        return $result;
    }
}
