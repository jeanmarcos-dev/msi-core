<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventorySales;

use Magento\InventoryIndexer\Model\Queue\EnqueueSalabilityUpdate;
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
     * @var GetAssignedStockIdForWebsiteInterface
     */
    private $getAssignedStockIdForWebsite;

    /**
     * @var EnqueueSalabilityUpdate
     */
    private $enqueueSalabilityUpdate;

    /**
     * @param GetAssignedStockIdForWebsiteInterface $getAssignedStockIdForWebsite
     * @param EnqueueSalabilityUpdate $enqueueSalabilityUpdate
     */
    public function __construct(
        GetAssignedStockIdForWebsiteInterface $getAssignedStockIdForWebsite,
        EnqueueSalabilityUpdate $enqueueSalabilityUpdate
    ) {
        $this->getAssignedStockIdForWebsite = $getAssignedStockIdForWebsite;
        $this->enqueueSalabilityUpdate = $enqueueSalabilityUpdate;
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
        $this->enqueueSalabilityUpdate->execute(
            $this->getAssignedStockIdForWebsite->execute($salesChannel->getCode()),
            $skus
        );
    }
}
