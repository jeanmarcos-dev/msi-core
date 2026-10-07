<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemDataBySkusAndSources;
use Magento\InventorySales\Plugin\InventoryCatalog\ValidateSalableQtyOnTransferInventoryPartiallyPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidateSalableQtyOnTransferInventoryPartiallyPluginTest extends TestCase
{
    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $config;

    /**
     * @var GetSourceItemDataBySkusAndSources|MockObject
     */
    private $getSourceItemData;

    /**
     * @var GetReservationsQuantityBySkusAndSources|MockObject
     */
    private $getReservationsQuantity;

    /**
     * @var ValidateSalableQtyOnTransferInventoryPartiallyPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(SourceReservationsConfig::class);
        $this->getSourceItemData = $this->createMock(GetSourceItemDataBySkusAndSources::class);
        $this->getReservationsQuantity = $this->createMock(GetReservationsQuantityBySkusAndSources::class);
        $this->plugin = new ValidateSalableQtyOnTransferInventoryPartiallyPlugin(
            $this->config,
            $this->getSourceItemData,
            $this->getReservationsQuantity
        );
    }

    public function testRejectsUnitsReservedAtTheOrigin(): void
    {
        $this->givenOrigin(3.0, -4.0);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Requested transfer amount for sku SKU-1 is not available');

        $this->transfer(3.0);
    }

    public function testAcceptsTheUnreservedPartOfTheOrigin(): void
    {
        $this->givenOrigin(5.0, -2.0);

        $this->transfer(3.0);

        $this->addToAssertionCount(1);
    }

    public function testRejectsATransferOutOfAnOriginWithoutPhysicalStock(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->getSourceItemData->method('execute')->willReturn([]);
        $this->getReservationsQuantity->method('execute')->willReturn([]);

        $this->expectException(ValidationException::class);

        $this->transfer(1.0);
    }

    public function testChecksNothingWhenSourceReservationsAreDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->getSourceItemData->expects(self::never())->method('execute');
        $this->getReservationsQuantity->expects(self::never())->method('execute');

        $this->transfer(3.0);
    }

    private function givenOrigin(float $quantity, float $reserved): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->getSourceItemData->method('execute')->with(['SKU-1'], ['slr_a'])
            ->willReturn(['slr_a' => ['SKU-1' => ['quantity' => $quantity, 'status' => 1]]]);
        $this->getReservationsQuantity->method('execute')->with(['SKU-1'], ['slr_a'])
            ->willReturn(['slr_a' => ['SKU-1' => $reserved]]);
    }

    private function transfer(float $qty): void
    {
        $item = $this->createMock(PartialInventoryTransferItemInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getQty')->willReturn($qty);

        $this->plugin->beforeExecute($this->createMock(TransferInventoryPartially::class), $item, 'slr_a', 'slr_b');
    }
}
