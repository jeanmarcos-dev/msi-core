<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Plugin\Sales\ResourceModel\Order\Shipment;

use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\InventoryShipping\Model\ResourceModel\ShipmentSource\SaveShipmentSource;
use Magento\InventoryShipping\Plugin\Sales\ResourceModel\Order\Shipment\SaveSourceForShipmentPlugin;
use Magento\Sales\Api\Data\ShipmentExtensionInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SaveSourceForShipmentPluginTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var SaveShipmentSource|MockObject
     */
    private $saveShipmentSource;

    /**
     * @var SaveSourceForShipmentPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->saveShipmentSource = $this->createMock(SaveShipmentSource::class);
        $this->plugin = new SaveSourceForShipmentPlugin($this->saveShipmentSource);
    }

    public function testAShipmentWithoutExtensionAttributesKeepsItsStoredSource(): void
    {
        $this->saveShipmentSource->expects(self::never())->method('execute');

        $this->afterSave($this->shipment(null));
    }

    public function testAShipmentWhoseExtensionHasNoSourceKeepsItsStoredSource(): void
    {
        $this->saveShipmentSource->expects(self::never())->method('execute');

        $this->afterSave($this->shipment($this->extension(null)));
    }

    public function testAnExplicitSourceIsStored(): void
    {
        $this->saveShipmentSource->expects(self::once())->method('execute')->with(42, 'sri_b');

        $this->afterSave($this->shipment($this->extension('sri_b')));
    }

    private function afterSave(Shipment $shipment): void
    {
        $resource = $this->createStub(ShipmentResource::class);

        self::assertSame($resource, $this->plugin->afterSave($resource, $resource, $shipment));
    }

    private function shipment(?ShipmentExtensionInterface $extension): Shipment&Stub
    {
        $shipment = $this->createStub(Shipment::class);
        $shipment->method('getId')->willReturn('42');
        $shipment->method('getExtensionAttributes')->willReturn($extension);

        return $shipment;
    }

    private function extension(?string $sourceCode): ShipmentExtensionInterface&MockObject
    {
        $extension = $this->createPartialMockWithReflection(
            ShipmentExtensionInterface::class,
            ['getSourceCode', 'setSourceCode']
        );
        $extension->expects(self::once())->method('getSourceCode')->willReturn($sourceCode);

        return $extension;
    }
}
