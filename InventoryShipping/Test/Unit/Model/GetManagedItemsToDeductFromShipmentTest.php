<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Model;

use Magento\Framework\Phrase;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\GetManagedItemsToDeductFromShipment;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetManagedItemsToDeductFromShipmentTest extends TestCase
{
    /**
     * @var GetItemsToDeductFromShipment|MockObject
     */
    private $getItemsToDeduct;

    /**
     * @var GetStockItemConfigurationInterface|MockObject
     */
    private $getStockItemConfiguration;

    /**
     * @var GetManagedItemsToDeductFromShipment
     */
    private $model;

    protected function setUp(): void
    {
        $this->getItemsToDeduct = $this->createMock(GetItemsToDeductFromShipment::class);
        $this->getStockItemConfiguration = $this->createMock(GetStockItemConfigurationInterface::class);
        $this->model = new GetManagedItemsToDeductFromShipment(
            $this->getItemsToDeduct,
            $this->getStockItemConfiguration
        );
    }

    public function testDropsTheItemsWhoseStockIsNotManaged(): void
    {
        $this->givenItemsToDeduct(['SKU-1' => 2.0, 'SKU-U' => 1.0, 'SKU-2' => 3.0]);
        $this->getStockItemConfiguration->method('execute')
            ->willReturnCallback(fn (string $sku, int $stockId) => $this->configuration($sku !== 'SKU-U'));

        $items = $this->model->execute($this->shipment(), 5);

        self::assertSame(['SKU-1' => 2.0, 'SKU-2' => 3.0], $this->qtyBySku($items));
    }

    public function testReadsTheConfigurationOfTheGivenStock(): void
    {
        $this->givenItemsToDeduct(['SKU-1' => 2.0]);
        $this->getStockItemConfiguration->expects(self::once())->method('execute')->with('SKU-1', 7)
            ->willReturn($this->configuration(true));

        self::assertSame(['SKU-1' => 2.0], $this->qtyBySku($this->model->execute($this->shipment(), 7)));
    }

    public function testKeepsAnItemWhoseSkuIsNotAssignedToTheStock(): void
    {
        $this->givenItemsToDeduct(['SKU-X' => 1.0]);
        $this->getStockItemConfiguration->method('execute')
            ->willThrowException(new SkuIsNotAssignedToStockException(new Phrase('not assigned')));

        self::assertSame(['SKU-X' => 1.0], $this->qtyBySku($this->model->execute($this->shipment(), 5)));
    }

    public function testReturnsNothingWhenNoItemHasManagedStock(): void
    {
        $this->givenItemsToDeduct(['SKU-U' => 1.0]);
        $this->getStockItemConfiguration->method('execute')->willReturn($this->configuration(false));

        self::assertSame([], $this->model->execute($this->shipment(), 5));
    }

    private function shipment(): Shipment
    {
        return $this->createMock(Shipment::class);
    }

    private function configuration(bool $isManageStock): StockItemConfigurationInterface
    {
        $configuration = $this->createMock(StockItemConfigurationInterface::class);
        $configuration->method('isManageStock')->willReturn($isManageStock);
        return $configuration;
    }

    private function givenItemsToDeduct(array $qtyBySku): void
    {
        $items = [];
        foreach ($qtyBySku as $sku => $qty) {
            $item = $this->createMock(ItemToDeductInterface::class);
            $item->method('getSku')->willReturn((string)$sku);
            $item->method('getQty')->willReturn($qty);
            $items[] = $item;
        }
        $this->getItemsToDeduct->method('execute')->willReturn($items);
    }

    private function qtyBySku(array $items): array
    {
        $qtyBySku = [];
        foreach ($items as $item) {
            $qtyBySku[$item->getSku()] = $item->getQty();
        }
        return $qtyBySku;
    }
}
