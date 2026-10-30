<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventorySales;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\Queue\ReservationDataFactory;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockIdsSharingSourcesWithStock;
use Magento\InventoryIndexer\Plugin\InventorySales\EnqueueAfterPlaceReservationsForSalesEvent;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnqueueAfterPlaceReservationsForSalesEventTest extends TestCase
{
    private const TOPIC = 'inventory.reservations.updateSalabilityStatus';

    /**
     * @var PublisherInterface|MockObject
     */
    private $publisher;

    /**
     * @var GetAssignedStockIdForWebsiteInterface|MockObject
     */
    private $getAssignedStockIdForWebsite;

    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $sourceReservationsConfig;

    /**
     * @var GetStockIdsSharingSourcesWithStock|MockObject
     */
    private $getStockIdsSharingSourcesWithStock;

    /**
     * @var EnqueueAfterPlaceReservationsForSalesEvent
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->getAssignedStockIdForWebsite = $this->createStub(GetAssignedStockIdForWebsiteInterface::class);
        $this->getAssignedStockIdForWebsite->method('execute')->willReturn(10);
        $this->sourceReservationsConfig = $this->createStub(SourceReservationsConfig::class);
        $this->getStockIdsSharingSourcesWithStock = $this->createMock(GetStockIdsSharingSourcesWithStock::class);
        $reservationDataFactory = $this->createStub(ReservationDataFactory::class);
        $reservationDataFactory->method('create')->willReturnCallback(
            static fn (array $data): ReservationData => new ReservationData($data['skus'], $data['stock'])
        );
        $this->plugin = new EnqueueAfterPlaceReservationsForSalesEvent(
            $this->publisher,
            $this->getAssignedStockIdForWebsite,
            $reservationDataFactory,
            $this->sourceReservationsConfig,
            $this->getStockIdsSharingSourcesWithStock
        );
    }

    public function testPublishesOnlyForTheStockOfTheSalesChannelWhenSourceReservationsAreOff(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->getStockIdsSharingSourcesWithStock->expects(self::never())->method('execute');
        $this->expectPublishedStocks([10]);

        $this->afterPlace(['SKU-1', 'SKU-2']);
    }

    public function testPublishesForEveryStockSharingASourceWhenSourceReservationsAreOn(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getStockIdsSharingSourcesWithStock->expects(self::once())
            ->method('execute')
            ->with(10, ['SKU-1', 'SKU-2'])
            ->willReturn([10, 30, 40]);
        $this->expectPublishedStocks([10, 30, 40]);

        $this->afterPlace(['SKU-1', 'SKU-2']);
    }

    public function testAlwaysPublishesForTheStockOfTheSalesChannelOnceEvenWhenNoSourceHoldsTheSkus(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getStockIdsSharingSourcesWithStock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([30, 30]);
        $this->expectPublishedStocks([10, 30]);

        $this->afterPlace(['SKU-1']);
    }

    public function testCarriesTheSkusOfTheSalesEventInEveryMessage(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getStockIdsSharingSourcesWithStock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([10, 30]);
        $skus = [];
        $this->publisher->expects(self::atLeastOnce())->method('publish')->willReturnCallback(
            static function (string $topic, ReservationData $data) use (&$skus): void {
                $skus[$data->getStock()] = $data->getSkus();
            }
        );

        $this->afterPlace(['SKU-1', 'SKU-2']);

        self::assertSame([10 => ['SKU-1', 'SKU-2'], 30 => ['SKU-1', 'SKU-2']], $skus);
    }

    /**
     * @param int[] $stockIds
     */
    private function expectPublishedStocks(array $stockIds): void
    {
        $published = [];
        $this->publisher->expects(self::exactly(count($stockIds)))
            ->method('publish')
            ->willReturnCallback(
                function (string $topic, ReservationData $data) use (&$published, $stockIds): void {
                    self::assertSame(self::TOPIC, $topic);
                    $published[] = $data->getStock();
                    if (count($published) === count($stockIds)) {
                        self::assertSame($stockIds, $published);
                    }
                }
            );
    }

    /**
     * @param string[] $skus
     */
    private function afterPlace(array $skus): void
    {
        $items = array_map(function (string $sku): ItemToSellInterface {
            $item = $this->createStub(ItemToSellInterface::class);
            $item->method('getSku')->willReturn($sku);

            return $item;
        }, $skus);
        $salesChannel = $this->createStub(SalesChannelInterface::class);
        $salesChannel->method('getCode')->willReturn('base');

        $this->plugin->afterExecute(
            $this->createStub(PlaceReservationsForSalesEventInterface::class),
            null,
            $items,
            $salesChannel
        );
    }
}
