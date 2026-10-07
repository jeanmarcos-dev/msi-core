<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Plugin\Sales\Order\Validation;

use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\InventoryShipping\Plugin\Sales\Order\Validation\ValidateSourceOnShipOrderPlugin;
use Magento\Sales\Api\Data\ShipmentExtensionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Validation\ShipOrderInterface;
use Magento\Sales\Model\ValidatorResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidateSourceOnShipOrderPluginTest extends TestCase
{
    /**
     * @var ResolveShipmentSourceCode|MockObject
     */
    private $resolveShipmentSourceCode;

    /**
     * @var ValidateSourceOnShipOrderPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->resolveShipmentSourceCode = $this->createMock(ResolveShipmentSourceCode::class);
        $this->plugin = new ValidateSourceOnShipOrderPlugin($this->resolveShipmentSourceCode);
    }

    public function testReportsAShipmentWhoseSourceCannotBeResolved(): void
    {
        $this->resolveShipmentSourceCode->method('execute')->willReturn(null);

        $result = $this->validate($this->shipment(null));

        self::assertSame(
            ['Specify the source to ship from: no single source of the order stock can ship every item '
                . 'of this shipment.'],
            array_map('strval', $result->getMessages())
        );
    }

    public function testAcceptsAShipmentWhoseSourceCanBeResolved(): void
    {
        $this->resolveShipmentSourceCode->method('execute')->willReturn('slr_a');

        self::assertFalse($this->validate($this->shipment(null))->hasMessages());
    }

    public function testAcceptsAShipmentWithAnExplicitSource(): void
    {
        $extension = $this->createMock(ShipmentExtensionInterface::class);
        $extension->method('getSourceCode')->willReturn('slr_b');
        $this->resolveShipmentSourceCode->expects(self::never())->method('execute');

        self::assertFalse($this->validate($this->shipment($extension))->hasMessages());
    }

    private function validate(Shipment $shipment): ValidatorResult
    {
        return $this->plugin->afterValidate(
            $this->createMock(ShipOrderInterface::class),
            new ValidatorResult(),
            $this->createMock(Order::class),
            $shipment
        );
    }

    /**
     * @return Shipment|MockObject
     */
    private function shipment(?ShipmentExtensionInterface $extension)
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getExtensionAttributes')->willReturn($extension);
        return $shipment;
    }
}
