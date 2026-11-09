<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryApi\StockRepository;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockExtensionInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventorySales\Model\GetUnassignedSalesChannelsForStock;
use Magento\InventorySales\Plugin\InventoryApi\StockRepository\SaveSalesChannelsLinksPlugin;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Model\GetAssignedSalesChannelsForStockInterface;
use Magento\InventorySalesApi\Model\ReplaceSalesChannelsForStockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SaveSalesChannelsLinksPluginTest extends TestCase
{
    private const STOCK_ID = 10;

    public function testMovesADroppedWebsiteToStock1(): void
    {
        $dropped = $this->createStub(SalesChannelInterface::class);
        $onStock1 = $this->createStub(SalesChannelInterface::class);
        $subject = $this->createStub(StockRepositoryInterface::class);
        $subject->method('get')->willReturn($this->createStub(StockInterface::class));

        $replacements = $this->afterSave($subject, $dropped, [$onStock1]);

        $this->assertSame([[[], self::STOCK_ID], [[$dropped, $onStock1], 1]], $replacements);
    }

    public function testLeavesADroppedWebsiteWithoutStockWhenStock1DoesNotExist(): void
    {
        $subject = $this->createStub(StockRepositoryInterface::class);
        $subject->method('get')->willThrowException(
            new NoSuchEntityException(__('Stock with id "%value" does not exist.', ['value' => 1]))
        );

        $replacements = $this->afterSave($subject, $this->createStub(SalesChannelInterface::class), []);

        $this->assertSame([[[], self::STOCK_ID]], $replacements);
    }

    private function afterSave(
        StockRepositoryInterface $subject,
        SalesChannelInterface $dropped,
        array $channelsOfStock1
    ): array {
        $extension = $this->createStub(StockExtensionInterface::class);
        $extension->method('getSalesChannels')->willReturn([]);
        $stock = $this->createStub(StockInterface::class);
        $stock->method('getExtensionAttributes')->willReturn($extension);

        $replacements = [];
        $replace = $this->createStub(ReplaceSalesChannelsForStockInterface::class);
        $replace->method('execute')->willReturnCallback(
            function (array $channels, int $stockId) use (&$replacements): void {
                $replacements[] = [$channels, $stockId];
            }
        );
        $getAssigned = $this->createStub(GetAssignedSalesChannelsForStockInterface::class);
        $getAssigned->method('execute')->willReturn($channelsOfStock1);
        $defaultStockProvider = $this->createStub(DefaultStockProviderInterface::class);
        $defaultStockProvider->method('getId')->willReturn(1);
        $getUnassigned = $this->createStub(GetUnassignedSalesChannelsForStock::class);
        $getUnassigned->method('execute')->willReturn([$dropped]);

        $plugin = new SaveSalesChannelsLinksPlugin(
            $replace,
            $this->createStub(LoggerInterface::class),
            $getAssigned,
            $defaultStockProvider,
            $getUnassigned
        );

        $this->assertSame(self::STOCK_ID, $plugin->afterSave($subject, self::STOCK_ID, $stock));

        return $replacements;
    }
}
