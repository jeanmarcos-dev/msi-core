<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryShipping;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetPendingSourceReservations;
use Magento\InventorySales\Plugin\InventoryShipping\PreferAllocatedSourceOnResolveShipmentSourceCodePlugin;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreferAllocatedSourceOnResolveShipmentSourceCodePluginTest extends TestCase
{
    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $config;

    /**
     * @var GetItemsToDeductFromShipment|MockObject
     */
    private $getItemsToDeduct;

    /**
     * @var GetPendingSourceReservations|MockObject
     */
    private $getPendingSourceReservations;

    /**
     * @var PreferAllocatedSourceOnResolveShipmentSourceCodePlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(SourceReservationsConfig::class);
        $this->getItemsToDeduct = $this->createMock(GetItemsToDeductFromShipment::class);
        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(5);
        $stockResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockResolver->method('execute')->with(1)->willReturn($stock);
        $this->getPendingSourceReservations = $this->createMock(GetPendingSourceReservations::class);
        $sources = [];
        foreach (['slr_a', 'slr_b'] as $sourceCode) {
            $source = $this->createMock(SourceInterface::class);
            $source->method('getSourceCode')->willReturn($sourceCode);
            $sources[] = $source;
        }
        $getSources = $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class);
        $getSources->method('execute')->with(5)->willReturn($sources);

        $this->plugin = new PreferAllocatedSourceOnResolveShipmentSourceCodePlugin(
            $this->config,
            $this->getItemsToDeduct,
            $stockResolver,
            $this->getPendingSourceReservations,
            $getSources
        );
    }

    public function testShipsFromTheSourceTheOrderReservedForEveryItem(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->givenItemsToDeduct(['SKU-1' => 2.0, 'SKU-2' => 1.0]);
        $this->getPendingSourceReservations->method('execute')->with('000000042', ['SKU-1', 'SKU-2'], 5)
            ->willReturn([
                'SKU-1' => ['slr_a' => -1.0, 'slr_b' => -3.0],
                'SKU-2' => ['slr_b' => -1.0],
            ]);

        self::assertSame('slr_b', $this->resolve(fn () => self::fail('the allocation must win')));
    }

    public function testPrefersTheHigherPrioritySourceWhenSeveralCoverTheShipment(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->givenItemsToDeduct(['SKU-1' => 1.0]);
        $this->getPendingSourceReservations->method('execute')
            ->willReturn(['SKU-1' => ['slr_b' => -2.0, 'slr_a' => -2.0]]);

        self::assertSame('slr_a', $this->resolve(fn () => 'proceeded'));
    }

    public function testFallsBackWhenNoAllocatedSourceCoversTheShipment(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->givenItemsToDeduct(['SKU-1' => 4.0]);
        $this->getPendingSourceReservations->method('execute')
            ->willReturn(['SKU-1' => ['slr_a' => -3.0, 'slr_b' => -1.0]]);

        self::assertSame('proceeded', $this->resolve(fn () => 'proceeded'));
    }

    public function testFallsBackWhenSourceReservationsAreDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->getPendingSourceReservations->expects(self::never())->method('execute');

        self::assertSame('proceeded', $this->resolve(fn () => 'proceeded'));
    }

    private function resolve(callable $proceed): ?string
    {
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $order = $this->createMock(Order::class);
        $order->method('getStore')->willReturn($store);
        $order->method('getIncrementId')->willReturn('000000042');

        return $this->plugin->aroundExecute(
            $this->createMock(ResolveShipmentSourceCode::class),
            $proceed,
            $this->createMock(Shipment::class),
            $order
        );
    }

    private function givenItemsToDeduct(array $qtyBySku): void
    {
        $items = [];
        foreach ($qtyBySku as $sku => $qty) {
            $item = $this->createMock(ItemToDeductInterface::class);
            $item->method('getSku')->willReturn($sku);
            $item->method('getQty')->willReturn($qty);
            $items[] = $item;
        }
        $this->getItemsToDeduct->method('execute')->willReturn($items);
    }
}
