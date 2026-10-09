<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\InventoryAdjustmentAdminUi\Ui\DataProvider\Product\Form\Modifier\AdjustmentHistory;
use PHPUnit\Framework\TestCase;

class AdjustmentHistoryTest extends TestCase
{
    public function testASavedProductGetsAClosedSectionWithItsOwnHistory(): void
    {
        $meta = $this->modifier(7, 'SKU-1', true)->modifyMeta(['sources' => []]);

        $section = $meta['adjustment_history']['arguments']['data']['config'];
        self::assertFalse($section['opened']);
        self::assertTrue($section['collapsible']);
        $listing = $meta['adjustment_history']['children']['adjustment_history_listing']['arguments']['data']['config'];
        self::assertFalse($listing['autoRender']);
        self::assertSame('inventory_adjustment_product_listing', $listing['ns']);
        self::assertSame('SKU-1', $listing['params']['sku']);
        self::assertSame('https://admin/mui/index/render', $listing['render_url']);
        self::assertArrayHasKey('sources', $meta);
    }

    public function testANewProductGetsNoSection(): void
    {
        self::assertSame(['sources' => []], $this->modifier(null, '', true)->modifyMeta(['sources' => []]));
    }

    public function testAUserWithoutThePermissionGetsNoSection(): void
    {
        self::assertSame(['sources' => []], $this->modifier(7, 'SKU-1', false)->modifyMeta(['sources' => []]));
    }

    public function testTheProductDataIsLeftAlone(): void
    {
        $data = ['7' => ['product' => []]];

        self::assertSame($data, $this->modifier(7, 'SKU-1', true)->modifyData($data));
    }

    private function modifier(?int $productId, string $sku, bool $allowed): AdjustmentHistory
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($productId);
        $product->method('getSku')->willReturn($sku);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->method('getProduct')->willReturn($product);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->with('Magento_InventoryAdjustment::view')->willReturn($allowed);
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(fn (string $route) => 'https://admin/' . $route);

        return new AdjustmentHistory($locator, $authorization, $urlBuilder);
    }
}
