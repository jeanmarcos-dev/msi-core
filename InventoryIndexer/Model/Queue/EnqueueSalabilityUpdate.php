<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockIdsSharingSourcesWithStock;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

/**
 * Enqueue the salability recalculation of the stocks whose index rows depend on reservations placed in a stock.
 */
class EnqueueSalabilityUpdate
{
    private const TOPIC_RESERVATIONS_UPDATE_SALABILITY_STATUS = 'inventory.reservations.updateSalabilityStatus';

    /**
     * @param PublisherInterface $publisher
     * @param ReservationDataFactory $reservationDataFactory
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param GetStockIdsSharingSourcesWithStock $getStockIdsSharingSourcesWithStock
     */
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly ReservationDataFactory $reservationDataFactory,
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly GetStockIdsSharingSourcesWithStock $getStockIdsSharingSourcesWithStock
    ) {
    }

    /**
     * Publish one message for the stock and, with source level reservations, for each stock sharing a source.
     *
     * @param int $stockId
     * @param string[] $skus
     * @return void
     */
    public function execute(int $stockId, array $skus): void
    {
        foreach ($this->getStockIdsToUpdate($stockId, $skus) as $stockIdToUpdate) {
            $this->publisher->publish(
                self::TOPIC_RESERVATIONS_UPDATE_SALABILITY_STATUS,
                $this->reservationDataFactory->create(['stock' => $stockIdToUpdate, 'skus' => $skus])
            );
        }
    }

    /**
     * Get the stocks to publish for, the given one first.
     *
     * @param int $stockId
     * @param string[] $skus
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
