<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Test\Unit\Model;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterface;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterface;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionItemInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\InventorySourceSelectionApi\Model\GetInventoryRequestFromOrder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResolveShipmentSourceCodeTest extends TestCase
{
    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSources;

    /**
     * @var GetItemsToDeductFromShipment|MockObject
     */
    private $getItemsToDeduct;

    /**
     * @var SourceSelectionServiceInterface|MockObject
     */
    private $sourceSelectionService;

    /**
     * @var GetInventoryRequestFromOrder|MockObject
     */
    private $getInventoryRequest;

    /**
     * @var ResolveShipmentSourceCode
     */
    private $model;

    protected function setUp(): void
    {
        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(5);
        $stockResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockResolver->method('execute')->with(1)->willReturn($stock);
        $this->getSources = $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class);
        $this->getItemsToDeduct = $this->createMock(GetItemsToDeductFromShipment::class);
        $itemRequestFactory = $this->createMock(ItemRequestInterfaceFactory::class);
        $itemRequestFactory->method('create')->willReturnCallback(function (array $data) {
            $itemRequest = $this->createMock(ItemRequestInterface::class);
            $itemRequest->method('getSku')->willReturn($data['sku']);
            $itemRequest->method('getQty')->willReturn($data['qty']);
            return $itemRequest;
        });
        $this->getInventoryRequest = $this->createMock(GetInventoryRequestFromOrder::class);
        $this->getInventoryRequest->method('execute')
            ->willReturn($this->createMock(InventoryRequestInterface::class));
        $this->sourceSelectionService = $this->createMock(SourceSelectionServiceInterface::class);
        $algorithmCode = $this->createMock(GetDefaultSourceSelectionAlgorithmCodeInterface::class);
        $algorithmCode->method('execute')->willReturn('priority');

        $this->model = new ResolveShipmentSourceCode(
            $stockResolver,
            $this->getSources,
            $this->getItemsToDeduct,
            $itemRequestFactory,
            $this->getInventoryRequest,
            $this->sourceSelectionService,
            $algorithmCode
        );
    }

    public function testUsesTheOnlySourceOfTheStock(): void
    {
        $this->givenSources(['slr_a']);
        $this->sourceSelectionService->expects(self::never())->method('execute');

        self::assertSame('slr_a', $this->model->execute($this->createMock(Shipment::class), $this->order()));
    }

    public function testUsesTheSourceSelectionWhenItShipsEverythingFromOneSource(): void
    {
        $this->givenSources(['slr_a', 'slr_b']);
        $this->givenItemsToDeduct(['SKU-1' => 2.0, 'SKU-2' => 1.0]);
        $this->givenSelection(true, ['slr_a' => 0.0, 'slr_b' => 3.0]);

        $requestedItems = [];
        $this->getInventoryRequest->expects(self::once())->method('execute')
            ->willReturnCallback(function (int $orderId, array $items) use (&$requestedItems) {
                foreach ($items as $item) {
                    $requestedItems[$item->getSku()] = $item->getQty();
                }
                return $this->createMock(InventoryRequestInterface::class);
            });

        self::assertSame('slr_b', $this->model->execute($this->createMock(Shipment::class), $this->order()));
        self::assertSame(['SKU-1' => 2.0, 'SKU-2' => 1.0], $requestedItems);
    }

    public function testResolvesNothingWhenTheSelectionSplitsTheShipment(): void
    {
        $this->givenSources(['slr_a', 'slr_b']);
        $this->givenItemsToDeduct(['SKU-1' => 5.0]);
        $this->givenSelection(true, ['slr_a' => 3.0, 'slr_b' => 2.0]);

        self::assertNull($this->model->execute($this->createMock(Shipment::class), $this->order()));
    }

    public function testResolvesNothingWhenTheStockCannotShipIt(): void
    {
        $this->givenSources(['slr_a', 'slr_b']);
        $this->givenItemsToDeduct(['SKU-1' => 9.0]);
        $this->givenSelection(false, ['slr_a' => 3.0]);

        self::assertNull($this->model->execute($this->createMock(Shipment::class), $this->order()));
    }

    public function testNeverFallsBackToTheDefaultSource(): void
    {
        $this->givenSources([]);

        self::assertNull($this->model->execute($this->createMock(Shipment::class), $this->order()));
    }

    public function testUsesTheFirstSourceWhenThereIsNothingToDeduct(): void
    {
        $this->givenSources(['slr_a', 'slr_b']);
        $this->givenItemsToDeduct([]);
        $this->sourceSelectionService->expects(self::never())->method('execute');

        self::assertSame('slr_a', $this->model->execute($this->createMock(Shipment::class), $this->order()));
    }

    private function order(): Order
    {
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $order = $this->createMock(Order::class);
        $order->method('getStore')->willReturn($store);
        $order->method('getId')->willReturn(42);
        return $order;
    }

    private function givenSources(array $sourceCodes): void
    {
        $sources = [];
        foreach ($sourceCodes as $sourceCode) {
            $source = $this->createMock(SourceInterface::class);
            $source->method('getSourceCode')->willReturn($sourceCode);
            $sources[] = $source;
        }
        $this->getSources->method('execute')->with(5)->willReturn($sources);
    }

    private function givenItemsToDeduct(array $qtyBySku): void
    {
        $items = [];
        foreach ($qtyBySku as $sku => $qty) {
            $item = $this->createMock(ItemToDeductInterface::class);
            $item->method('getSku')->willReturn($sku);
            $item->method('getQty')->willReturn($qty);
            $items[] = $item;
        }
        $this->getItemsToDeduct->method('execute')->willReturn($items);
    }

    private function givenSelection(bool $isShippable, array $qtyToDeductBySource): void
    {
        $items = [];
        foreach ($qtyToDeductBySource as $sourceCode => $qtyToDeduct) {
            $item = $this->createMock(SourceSelectionItemInterface::class);
            $item->method('getSourceCode')->willReturn($sourceCode);
            $item->method('getQtyToDeduct')->willReturn($qtyToDeduct);
            $items[] = $item;
        }
        $result = $this->createMock(SourceSelectionResultInterface::class);
        $result->method('isShippable')->willReturn($isShippable);
        $result->method('getSourceSelectionItems')->willReturn($items);
        $this->sourceSelectionService->method('execute')->willReturn($result);
    }
}
