<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySales\Model\GetSkusRequiringReservation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetSkusRequiringReservationTest extends TestCase
{
    /**
     * @var StockConfigurationInterface|MockObject
     */
    private $stockConfiguration;

    /**
     * @var GetStockItemConfigurationInterface|MockObject
     */
    private $getStockItemConfiguration;

    /**
     * @var GetSkusRequiringReservation
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->stockConfiguration = $this->createMock(StockConfigurationInterface::class);
        $this->getStockItemConfiguration = $this->createMock(GetStockItemConfigurationInterface::class);
        $this->model = new GetSkusRequiringReservation($this->getStockItemConfiguration, $this->stockConfiguration);
    }

    public function testKeepsManagedSku(): void
    {
        $this->stockConfiguration->method('canSubtractQty')->willReturn(true);
        $this->givenManageStock(['managed' => true], 5);

        $this->assertSame(['managed'], $this->model->execute(['managed'], 5));
    }

    public function testDropsSkuWithoutManagedStock(): void
    {
        $this->stockConfiguration->method('canSubtractQty')->willReturn(true);
        $this->givenManageStock(['free' => false], 5);

        $this->assertSame([], $this->model->execute(['free'], 5));
    }

    public function testReturnsNothingWhenStockIsNotSubtracted(): void
    {
        $this->stockConfiguration->method('canSubtractQty')->willReturn(false);
        $this->getStockItemConfiguration->expects($this->never())->method('execute');

        $this->assertSame([], $this->model->execute(['managed', 'other'], 5));
    }

    public function testKeepsOnlyManagedSkusOfAMixedSet(): void
    {
        $this->stockConfiguration->method('canSubtractQty')->willReturn(true);
        $this->givenManageStock(['a' => true, 'free' => false, '123' => true], 7);

        $this->assertSame(['a', '123'], $this->model->execute(['a', 'free', 123], 7));
    }

    /**
     * @param array<string,bool> $manageStockBySku
     * @param int $stockId
     * @return void
     */
    private function givenManageStock(array $manageStockBySku, int $stockId): void
    {
        $this->getStockItemConfiguration->method('execute')->willReturnCallback(
            function (string $sku, int $requestedStockId) use ($manageStockBySku, $stockId) {
                $this->assertSame($stockId, $requestedStockId);
                $configuration = $this->createMock(StockItemConfigurationInterface::class);
                $configuration->method('isManageStock')->willReturn($manageStockBySku[$sku]);

                return $configuration;
            }
        );
    }
}
