<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\Sales\OrderManagement;

use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Model\ReservationExecutionInterface;
use Magento\InventorySales\Plugin\Sales\OrderManagement\AppendReservationsAfterOrderPlacementPlugin;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;

class AppendReservationsAfterOrderPlacementPluginTest extends TestCase
{
    public function testReservesTheChildSimplesOfCompositeItemsAndNotTheirParents(): void
    {
        $skusById = [10 => 'lk_config', 11 => 'lk_s', 20 => 'lk_bundle', 30 => 'lk_free'];
        $types = ['lk_config' => 'configurable', 'lk_s' => 'simple', 'lk_bundle' => 'bundle', 'lk_free' => 'simple'];
        $order = $this->order([10 => 1.0, 11 => 1.0, 20 => 1.0, 30 => 2.0], 11, 1.0);

        $getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->willReturnCallback(
            fn(array $ids) => array_intersect_key($skusById, array_flip($ids))
        );
        $getProductTypesBySkus = $this->createMock(GetProductTypesBySkusInterface::class);
        $getProductTypesBySkus->method('execute')->willReturn($types);
        $isSourceItemManagementAllowed = $this->createMock(IsSourceItemManagementAllowedForProductTypeInterface::class);
        $isSourceItemManagementAllowed->method('execute')->willReturnCallback(fn(string $type) => $type === 'simple');
        $itemsToSellFactory = $this->createMock(ItemToSellInterfaceFactory::class);
        $itemsToSellFactory->method('create')->willReturnCallback(
            fn(array $data) => $this->createConfiguredMock(
                ItemToSellInterface::class,
                ['getSku' => $data['sku'], 'getQuantity' => $data['qty']]
            )
        );
        $reservationExecution = $this->createConfiguredMock(
            ReservationExecutionInterface::class,
            ['isDeferred' => true]
        );

        $reserved = [];
        $appendReservations = $this->createMock(AppendReservations::class);
        $appendReservations->expects($this->once())->method('reserve')->willReturnCallback(
            function ($websiteId, $itemsBySku, $receivedOrder, $itemsToSell) use (&$reserved) {
                $reserved = [$websiteId, $itemsBySku, $itemsToSell];
                return [
                    $this->createMock(SalesChannelInterface::class),
                    $this->createMock(SalesEventExtensionInterface::class),
                ];
            }
        );

        $plugin = new AppendReservationsAfterOrderPlacementPlugin(
            $this->createMock(PlaceReservationsForSalesEventInterface::class),
            $getSkusByProductIds,
            $this->createMock(SalesEventInterfaceFactory::class),
            $itemsToSellFactory,
            $getProductTypesBySkus,
            $isSourceItemManagementAllowed,
            $appendReservations,
            $reservationExecution
        );
        $plugin->aroundPlace($this->createMock(OrderManagementInterface::class), fn($received) => $received, $order);

        [$websiteId, $itemsBySku, $itemsToSell] = $reserved;
        $this->assertSame(3, $websiteId);
        $this->assertSame(['lk_s' => 2.0, 'lk_free' => 2.0], $itemsBySku);
        $this->assertSame(
            ['lk_s' => -2.0, 'lk_free' => -2.0],
            array_combine(
                array_map(fn($item) => $item->getSku(), $itemsToSell),
                array_map(fn($item) => $item->getQuantity(), $itemsToSell)
            )
        );
    }

    /**
     * @param array<int,float> $qtyByProductId
     * @param int $repeatedProductId
     * @param float $repeatedQty
     * @return OrderInterface
     */
    private function order(array $qtyByProductId, int $repeatedProductId, float $repeatedQty): OrderInterface
    {
        $items = [];
        foreach ($qtyByProductId as $productId => $qty) {
            $items[] = $this->item($productId, $qty);
        }
        $items[] = $this->item($repeatedProductId, $repeatedQty);

        $store = $this->createConfiguredMock(Store::class, ['getWebsiteId' => 3]);
        $order = $this->getMockBuilder(OrderInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['getStore'])
            ->getMockForAbstractClass();
        $order->method('getItems')->willReturn($items);
        $order->method('getStore')->willReturn($store);

        return $order;
    }

    /**
     * @param int $productId
     * @param float $qty
     * @return OrderItemInterface
     */
    private function item(int $productId, float $qty): OrderItemInterface
    {
        return $this->createConfiguredMock(
            OrderItemInterface::class,
            ['getProductId' => $productId, 'getQtyOrdered' => $qty]
        );
    }
}
