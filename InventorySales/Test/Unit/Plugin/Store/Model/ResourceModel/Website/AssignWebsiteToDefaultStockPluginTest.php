<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\Store\Model\ResourceModel\Website;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockExtensionInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventorySales\Plugin\Store\Model\ResourceModel\Website\AssignWebsiteToDefaultStockPlugin;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\Store\Model\ResourceModel\Website as WebsiteResourceModel;
use Magento\Store\Model\Website;
use PHPUnit\Framework\TestCase;

class AssignWebsiteToDefaultStockPluginTest extends TestCase
{
    public function testAssignsANewWebsiteToStock1(): void
    {
        $channel = $this->createStub(SalesChannelInterface::class);
        $extension = $this->createMock(StockExtensionInterface::class);
        $extension->method('getSalesChannels')->willReturn([]);
        $extension->expects($this->once())->method('setSalesChannels')->with([$channel]);
        $stock = $this->createStub(StockInterface::class);
        $stock->method('getExtensionAttributes')->willReturn($extension);

        $stockRepository = $this->createMock(StockRepositoryInterface::class);
        $stockRepository->method('get')->with(1)->willReturn($stock);
        $stockRepository->expects($this->once())->method('save')->with($stock);

        $this->afterSave($stockRepository, $channel);
    }

    public function testLeavesANewWebsiteWithoutStockWhenStock1DoesNotExist(): void
    {
        $stockRepository = $this->createMock(StockRepositoryInterface::class);
        $stockRepository->method('get')->with(1)->willThrowException(
            new NoSuchEntityException(__('Stock with id "%value" does not exist.', ['value' => 1]))
        );
        $stockRepository->expects($this->never())->method('save');

        $this->afterSave($stockRepository, $this->createStub(SalesChannelInterface::class));
    }

    private function afterSave(StockRepositoryInterface $stockRepository, SalesChannelInterface $channel): void
    {
        $defaultStockProvider = $this->createStub(DefaultStockProviderInterface::class);
        $defaultStockProvider->method('getId')->willReturn(1);
        $channelFactory = $this->createStub(SalesChannelInterfaceFactory::class);
        $channelFactory->method('create')->willReturn($channel);
        $getAssignedStockId = $this->createStub(GetAssignedStockIdForWebsiteInterface::class);
        $getAssignedStockId->method('execute')->willReturn(null);

        $website = $this->createStub(Website::class);
        $website->method('getCode')->willReturn('dss_web');
        $resource = $this->createStub(WebsiteResourceModel::class);

        $plugin = new AssignWebsiteToDefaultStockPlugin(
            $stockRepository,
            $defaultStockProvider,
            $channelFactory,
            $getAssignedStockId
        );

        $this->assertSame($resource, $plugin->afterSave($resource, $resource, $website));
    }
}
