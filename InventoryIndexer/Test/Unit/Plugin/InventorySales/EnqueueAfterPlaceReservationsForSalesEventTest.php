<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventorySales;

use Magento\InventoryIndexer\Model\Queue\EnqueueSalabilityUpdate;
use Magento\InventoryIndexer\Plugin\InventorySales\EnqueueAfterPlaceReservationsForSalesEvent;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use PHPUnit\Framework\TestCase;

class EnqueueAfterPlaceReservationsForSalesEventTest extends TestCase
{
    public function testEnqueuesTheSkusOfTheItemsForTheStockOfTheSalesChannel(): void
    {
        $getAssignedStockIdForWebsite = $this->createMock(GetAssignedStockIdForWebsiteInterface::class);
        $getAssignedStockIdForWebsite->expects(self::once())->method('execute')->with('base')->willReturn(10);
        $enqueueSalabilityUpdate = $this->createMock(EnqueueSalabilityUpdate::class);
        $enqueueSalabilityUpdate->expects(self::once())->method('execute')->with(10, ['SKU-1', 'SKU-2']);
        $plugin = new EnqueueAfterPlaceReservationsForSalesEvent(
            $getAssignedStockIdForWebsite,
            $enqueueSalabilityUpdate
        );
        $salesChannel = $this->createStub(SalesChannelInterface::class);
        $salesChannel->method('getCode')->willReturn('base');

        $plugin->afterExecute(
            $this->createStub(PlaceReservationsForSalesEventInterface::class),
            null,
            [$this->item('SKU-1'), $this->item('SKU-2')],
            $salesChannel
        );
    }

    private function item(string $sku): ItemToSellInterface
    {
        $item = $this->createStub(ItemToSellInterface::class);
        $item->method('getSku')->willReturn($sku);

        return $item;
    }
}
