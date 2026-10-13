<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventorySourceDeductionApi\Model\IsItemCouldBeDeductedByTypes;
use Magento\InventorySourceDeductionApi\Model\ItemToDeduct;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetItemsToDeductFromShipmentTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var Shipment|MockObject
     */
    private $shipment;

    /**
     * @var ShipmentItem|MockObject
     */
    private $shipmentItem;

    /**
     * @var Order|MockObject
     */
    private $order;

    /**
     * @var OrderItem|MockObject
     */
    private $orderItem;

    /**
     * @var GetSkuFromOrderItemInterface|MockObject
     */
    private $getSkuFromOrderItem;

    /**
     * @var Json|MockObject
     */
    private $jsonSerializer;

    /**
     * @var ItemToDeductInterfaceFactory|MockObject
     */
    private $itemToDeductFactory;

    /**
     * @var IsItemCouldBeDeductedByTypes|MockObject
     */
    private $itemCouldBeDeducted;

    /**
     * @var GetItemsToDeductFromShipment
     */
    private $model;

    protected function setUp(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('simple');

        $this->orderItem = $this->createPartialMockWithReflection(
            OrderItem::class,
            ['getItemId', 'getProduct', 'getProductType', 'getHasChildren', 'getIsQtyDecimal']
        );
        $this->orderItem->method('getItemId')->willReturn(7);
        $this->orderItem->method('getProduct')->willReturn($product);
        $this->orderItem->method('getProductType')->willReturn('simple');
        $this->orderItem->method('getHasChildren')->willReturn(false);
        $this->orderItem->method('getIsQtyDecimal')->willReturn(false);

        $this->order = $this->createMock(Order::class);
        $this->order->method('getAllItems')->willReturn([$this->orderItem]);

        $this->shipmentItem = $this->createMock(ShipmentItem::class);
        $this->shipmentItem->method('getOrderItemId')->willReturn(7);
        $this->shipmentItem->method('getQty')->willReturn(2);

        $this->shipment = $this->createMock(Shipment::class);

        $this->getSkuFromOrderItem = $this->createMock(GetSkuFromOrderItemInterface::class);
        $this->getSkuFromOrderItem->method('execute')->with($this->orderItem)->willReturn('SKU-7');

        $this->jsonSerializer = $this->createMock(Json::class);

        $this->itemToDeductFactory = $this->createMock(ItemToDeductInterfaceFactory::class);
        $this->itemToDeductFactory->method('create')->willReturnCallback(
            function (array $data) {
                return new ItemToDeduct($data['sku'], (float)$data['qty']);
            }
        );

        $this->itemCouldBeDeducted = $this->createMock(IsItemCouldBeDeductedByTypes::class);
        $this->itemCouldBeDeducted->method('execute')->willReturn(true);

        $this->model = new GetItemsToDeductFromShipment(
            $this->getSkuFromOrderItem,
            $this->jsonSerializer,
            $this->itemToDeductFactory,
            $this->itemCouldBeDeducted
        );
    }

    public function testResolvesOrderItemFromOrderWhenShipmentItemHasNoOrderItem(): void
    {
        $this->shipmentItem->method('getOrderItem')->willReturn(null);
        $this->shipment->method('getAllItems')->willReturn([$this->shipmentItem]);
        $this->shipment->method('getOrder')->willReturn($this->order);

        $result = $this->model->execute($this->shipment);

        self::assertCount(1, $result);
        self::assertSame('SKU-7', $result[0]->getSku());
        self::assertEquals(2, $result[0]->getQty());
    }

    public function testDoesNotLoadOrderWhenShipmentItemAlreadyHasOrderItem(): void
    {
        $this->shipmentItem->method('getOrderItem')->willReturn($this->orderItem);
        $this->shipment->method('getAllItems')->willReturn([$this->shipmentItem]);
        $this->shipment->expects(self::never())->method('getOrder');

        $result = $this->model->execute($this->shipment);

        self::assertCount(1, $result);
        self::assertSame('SKU-7', $result[0]->getSku());
        self::assertEquals(2, $result[0]->getQty());
    }

    public function testSkipsShipmentItemWhenOrderItemIdIsNotInOrder(): void
    {
        $missingShipmentItem = $this->createMock(ShipmentItem::class);
        $missingShipmentItem->method('getOrderItem')->willReturn(null);
        $missingShipmentItem->method('getOrderItemId')->willReturn(99);
        $missingShipmentItem->method('getQty')->willReturn(1);

        $this->shipmentItem->method('getOrderItem')->willReturn(null);
        $this->shipment->method('getAllItems')->willReturn([$missingShipmentItem, $this->shipmentItem]);
        $this->shipment->method('getOrder')->willReturn($this->order);

        $result = $this->model->execute($this->shipment);

        self::assertCount(1, $result);
        self::assertSame('SKU-7', $result[0]->getSku());
        self::assertEquals(2, $result[0]->getQty());
    }

    public function testStoresResolvedParentOrderItemOnShipmentItemForComplexProcessing(): void
    {
        $child = $this->createPartialMockWithReflection(
            OrderItem::class,
            ['getIsVirtual', 'getLockedDoShip', 'getProductOptions']
        );
        $child->method('getIsVirtual')->willReturn(false);
        $child->method('getLockedDoShip')->willReturn(false);
        $child->method('getProductOptions')->willReturn([]);

        $parent = $this->createPartialMockWithReflection(
            OrderItem::class,
            [
                'getItemId',
                'getProduct',
                'getProductType',
                'getHasChildren',
                'isDummy',
                'getChildrenItems',
                'getIsQtyDecimal',
            ]
        );
        $parent->method('getItemId')->willReturn(7);
        $parent->method('getProduct')->willReturn($this->orderItem->getProduct());
        $parent->method('getProductType')->willReturn('configurable');
        $parent->method('getHasChildren')->willReturn(true);
        $parent->method('isDummy')->with(true)->willReturn(false);
        $parent->method('getChildrenItems')->willReturn([$child]);
        $parent->method('getIsQtyDecimal')->willReturn(false);

        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn([$parent]);

        $getSkuFromOrderItem = $this->createMock(GetSkuFromOrderItemInterface::class);
        $getSkuFromOrderItem->method('execute')->with($parent)->willReturn('CONFIGURABLE-SKU');

        $shipmentItem = $this->createMock(ShipmentItem::class);
        $shipmentItem->method('getOrderItemId')->willReturn(7);
        $shipmentItem->method('getQty')->willReturn(3);
        $resolved = null;
        $shipmentItem->expects(self::once())->method('setOrderItem')->with($parent)->willReturnCallback(
            function ($orderItem) use (&$resolved, $shipmentItem) {
                $resolved = $orderItem;
                return $shipmentItem;
            }
        );
        $shipmentItem->method('getOrderItem')->willReturnCallback(function () use (&$resolved) {
            return $resolved;
        });

        $this->shipment->method('getAllItems')->willReturn([$shipmentItem]);
        $this->shipment->method('getOrder')->willReturn($order);

        $model = new GetItemsToDeductFromShipment(
            $getSkuFromOrderItem,
            $this->jsonSerializer,
            $this->itemToDeductFactory,
            $this->itemCouldBeDeducted
        );

        $result = $model->execute($this->shipment);

        self::assertCount(1, $result);
        self::assertSame('CONFIGURABLE-SKU', $result[0]->getSku());
        self::assertEquals(3, $result[0]->getQty());
    }
}
