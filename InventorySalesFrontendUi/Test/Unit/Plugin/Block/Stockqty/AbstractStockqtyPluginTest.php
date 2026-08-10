<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesFrontendUi\Test\Unit\Plugin\Block\Stockqty;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Block\Stockqty\AbstractStockqty;
use Magento\InventoryCatalogFrontendUi\Model\IsSalableQtyAvailableForDisplaying;
use Magento\InventoryCatalogFrontendUi\Model\IsSalableQtyThresholdReached;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventorySalesFrontendUi\Plugin\Block\Stockqty\AbstractStockqtyPlugin;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractStockqtyPluginTest extends TestCase
{
    /**
     * @var GetStockItemConfigurationInterface|MockObject
     */
    private $getStockItemConfiguration;

    /**
     * @var GetProductSalableQtyInterface|MockObject
     */
    private $getProductSalableQty;

    /**
     * @var IsSalableQtyThresholdReached|MockObject
     */
    private $thresholdReached;

    /**
     * @var AbstractStockqty|MockObject
     */
    private $subject;

    /**
     * @var AbstractStockqtyPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getStore')->willReturn($store);

        $this->subject = $this->createMock(AbstractStockqty::class);
        $this->subject->method('getProduct')->willReturn($product);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(10);
        $stockByWebsiteId = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockByWebsiteId->method('execute')->willReturn($stock);

        $productTypeAllowed = $this->createMock(IsSourceItemManagementAllowedForProductTypeInterface::class);
        $productTypeAllowed->method('execute')->willReturn(true);

        $this->getStockItemConfiguration = $this->createMock(GetStockItemConfigurationInterface::class);
        $this->getProductSalableQty = $this->createMock(GetProductSalableQtyInterface::class);
        $this->thresholdReached = $this->createMock(IsSalableQtyThresholdReached::class);

        $this->plugin = new AbstractStockqtyPlugin(
            $stockByWebsiteId,
            $this->getStockItemConfiguration,
            $this->getProductSalableQty,
            $productTypeAllowed,
            $this->createMock(IsSalableQtyAvailableForDisplaying::class),
            $this->thresholdReached
        );
    }

    public function testAProductOutsideTheStockHidesTheMessageInsteadOfBreakingThePage(): void
    {
        $this->getStockItemConfiguration->method('execute')
            ->willThrowException(new SkuIsNotAssignedToStockException(__('nope')));

        self::assertFalse($this->plugin->aroundIsMsgVisible($this->subject, static fn () => true));
    }

    public function testTheMessageFollowsTheThresholdWhenTheProductIsInTheStock(): void
    {
        $configuration = $this->createMock(StockItemConfigurationInterface::class);
        $configuration->method('isManageStock')->willReturn(true);
        $this->getStockItemConfiguration->method('execute')->willReturn($configuration);
        $this->getProductSalableQty->method('execute')->willReturn(5.0);
        $this->thresholdReached->method('execute')->willReturn(true);

        self::assertTrue($this->plugin->aroundIsMsgVisible($this->subject, static fn () => false));
    }

    public function testTheMessageStaysHiddenWhenStockIsNotManaged(): void
    {
        $configuration = $this->createMock(StockItemConfigurationInterface::class);
        $configuration->method('isManageStock')->willReturn(false);
        $this->getStockItemConfiguration->method('execute')->willReturn($configuration);
        $this->getProductSalableQty->method('execute')->willReturn(5.0);
        $this->thresholdReached->method('execute')->willReturn(true);

        self::assertFalse($this->plugin->aroundIsMsgVisible($this->subject, static fn () => true));
    }
}
