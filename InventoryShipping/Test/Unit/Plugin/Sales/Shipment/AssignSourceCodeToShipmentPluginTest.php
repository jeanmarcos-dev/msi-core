<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Plugin\Sales\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\InventoryShipping\Plugin\Sales\Shipment\AssignSourceCodeToShipmentPlugin;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Sales\Api\Data\ShipmentExtensionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AssignSourceCodeToShipmentPluginTest extends TestCase
{
    /**
     * @var RequestInterface|MockObject
     */
    private $request;

    /**
     * @var ResolveShipmentSourceCode|MockObject
     */
    private $resolveShipmentSourceCode;

    /**
     * @var ShipmentExtensionInterface|MockObject
     */
    private $extension;

    /**
     * @var AssignSourceCodeToShipmentPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->resolveShipmentSourceCode = $this->createMock(ResolveShipmentSourceCode::class);
        $this->extension = $this->createMock(ShipmentExtensionInterface::class);
        $extensionFactory = $this->createMock(ShipmentExtensionFactory::class);
        $extensionFactory->method('create')->willReturn($this->extension);

        $this->plugin = new AssignSourceCodeToShipmentPlugin(
            $this->request,
            $extensionFactory,
            $this->resolveShipmentSourceCode
        );
    }

    public function testUsesTheSourceChosenInTheRequest(): void
    {
        $this->request->method('getParam')->with('sourceCode')->willReturn('slr_b');
        $this->resolveShipmentSourceCode->expects(self::never())->method('execute');
        $this->extension->expects(self::once())->method('setSourceCode')->with('slr_b');

        $this->plugin->afterCreate(
            $this->createMock(ShipmentFactory::class),
            $this->createMock(Shipment::class),
            $this->createMock(Order::class)
        );
    }

    public function testResolvesTheSourceWhenTheRequestHasNone(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $order = $this->createMock(Order::class);
        $this->resolveShipmentSourceCode->method('execute')->with($shipment, $order)->willReturn('slr_a');
        $this->extension->expects(self::once())->method('setSourceCode')->with('slr_a');

        $this->plugin->afterCreate($this->createMock(ShipmentFactory::class), $shipment, $order);
    }

    public function testLeavesTheSourceUnsetWhenItCannotBeResolved(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $this->resolveShipmentSourceCode->method('execute')->willReturn(null);
        $shipment->expects(self::never())->method('setExtensionAttributes');

        $result = $this->plugin->afterCreate(
            $this->createMock(ShipmentFactory::class),
            $shipment,
            $this->createMock(Order::class)
        );

        self::assertSame($shipment, $result);
    }
}
