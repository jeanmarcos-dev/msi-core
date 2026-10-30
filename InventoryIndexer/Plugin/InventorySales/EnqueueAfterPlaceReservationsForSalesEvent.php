<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventorySales;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\Queue\ReservationDataFactory;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockIdsSharingSourcesWithStock;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;

/**
 * Enqueue reservations processing after appending in order to recalculate index salability status.
 */
class EnqueueAfterPlaceReservationsForSalesEvent
{
    /**
     * Queue topic name.
     */
    private const TOPIC_RESERVATIONS_UPDATE_SALABILITY_STATUS = 'inventory.reservations.updateSalabilityStatus';

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var GetAssignedStockIdForWebsiteInterface
     */
    private $getAssignedStockIdForWebsite;

    /**
     * @var ReservationDataFactory
     */
    private $reservationDataFactory;

    /**
     * @var SourceReservationsConfig
     */
    private $sourceReservationsConfig;

    /**
     * @var GetStockIdsSharingSourcesWithStock
     */
    private $getStockIdsSharingSourcesWithStock;

    /**
     * @param PublisherInterface $publisher
     * @param GetAssignedStockIdForWebsiteInterface $getAssignedStockIdForWebsite
     * @param ReservationDataFactory $reservationDataFactory
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param GetStockIdsSharingSourcesWithStock $getStockIdsSharingSourcesWithStock
     */
    public function __construct(
        PublisherInterface $publisher,
        GetAssignedStockIdForWebsiteInterface $getAssignedStockIdForWebsite,
        ReservationDataFactory $reservationDataFactory,
        SourceReservationsConfig $sourceReservationsConfig,
        GetStockIdsSharingSourcesWithStock $getStockIdsSharingSourcesWithStock
    ) {
        $this->publisher = $publisher;
        $this->getAssignedStockIdForWebsite = $getAssignedStockIdForWebsite;
        $this->reservationDataFactory = $reservationDataFactory;
        $this->sourceReservationsConfig = $sourceReservationsConfig;
        $this->getStockIdsSharingSourcesWithStock = $getStockIdsSharingSourcesWithStock;
    }

    /**
     * Publish reservation data for reindex.
     *
     * @param PlaceReservationsForSalesEventInterface $subject
     * @param void $result
     * @param ItemToSellInterface[] $items
     * @param SalesChannelInterface $salesChannel
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        PlaceReservationsForSalesEventInterface $subject,
        $result,
        array $items,
        SalesChannelInterface $salesChannel
    ): void {
        $skus = array_map(
            function (ItemToSellInterface $itemToSell): string {
                return $itemToSell->getSku();
            },
            $items
        );
        $stockId = $this->getAssignedStockIdForWebsite->execute($salesChannel->getCode());
        foreach ($this->getStockIdsToUpdate($stockId, $skus) as $stockIdToUpdate) {
            $this->publisher->publish(
                self::TOPIC_RESERVATIONS_UPDATE_SALABILITY_STATUS,
                $this->reservationDataFactory->create(['stock' => $stockIdToUpdate, 'skus' => $skus])
            );
        }
    }

    /**
     * Get the stocks whose index rows depend on the reservations placed in the given stock.
     *
     * @param int $stockId
     * @param string[] $skus
     *
     * @return int[]
     */
    private function getStockIdsToUpdate(int $stockId, array $skus): array
    {
        if (!$this->sourceReservationsConfig->isEnabled()) {
            return [$stockId];
        }

        return array_values(
            array_unique(
                array_merge([$stockId], $this->getStockIdsSharingSourcesWithStock->execute($stockId, $skus))
            )
        );
    }
}
