<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Plugin\Sales\ResourceModel\Order\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\InventoryShipping\Plugin\Sales\ResourceModel\Order\Shipment\ResolveSourceForShipmentPlugin;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Sales\Api\Data\ShipmentExtensionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResolveSourceForShipmentPluginTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var ResolveShipmentSourceCode|MockObject
     */
    private $resolveShipmentSourceCode;

    /**
     * @var ShipmentExtensionInterface|MockObject
     */
    private $extension;

    /**
     * @var ResolveSourceForShipmentPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->resolveShipmentSourceCode = $this->createMock(ResolveShipmentSourceCode::class);
        $this->extension = $this->createPartialMockWithReflection(
            ShipmentExtensionInterface::class,
            ['getSourceCode', 'setSourceCode']
        );
        $extensionFactory = $this->createMock(ShipmentExtensionFactory::class);
        $extensionFactory->method('create')->willReturn($this->extension);

        $this->plugin = new ResolveSourceForShipmentPlugin($this->resolveShipmentSourceCode, $extensionFactory);
    }

    public function testResolvesTheSourceOfANewShipmentThatHasNone(): void
    {
        $order = $this->createMock(Order::class);
        $shipment = $this->shipment(null, null, $order);
        $this->resolveShipmentSourceCode->method('execute')->with($shipment, $order)->willReturn('slr_b');
        $this->extension->expects(self::once())->method('setSourceCode')->with('slr_b');
        $shipment->expects(self::once())->method('setExtensionAttributes')->with($this->extension);

        $this->plugin->beforeSave($this->createMock(ShipmentResource::class), $shipment);
    }

    public function testRejectsANewShipmentWhoseSourceCannotBeResolved(): void
    {
        $shipment = $this->shipment(null, null, $this->createMock(Order::class));
        $this->resolveShipmentSourceCode->method('execute')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Specify the source to ship from');

        $this->plugin->beforeSave($this->createMock(ShipmentResource::class), $shipment);
    }

    public function testKeepsTheSourceAlreadyAssigned(): void
    {
        $this->extension->method('getSourceCode')->willReturn('slr_a');
        $shipment = $this->shipment(null, $this->extension, $this->createMock(Order::class));
        $this->resolveShipmentSourceCode->expects(self::never())->method('execute');

        $this->plugin->beforeSave($this->createMock(ShipmentResource::class), $shipment);
    }

    public function testLeavesExistingShipmentsAlone(): void
    {
        $shipment = $this->shipment('10', null, $this->createMock(Order::class));
        $this->resolveShipmentSourceCode->expects(self::never())->method('execute');

        $this->plugin->beforeSave($this->createMock(ShipmentResource::class), $shipment);
    }

    /**
     * @return Shipment|MockObject
     */
    private function shipment(?string $id, ?ShipmentExtensionInterface $extension, Order $order)
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getExtensionAttributes')->willReturn($extension);
        $shipment->method('getOrder')->willReturn($order);
        return $shipment;
    }
}
