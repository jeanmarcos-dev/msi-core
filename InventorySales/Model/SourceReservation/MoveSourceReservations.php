<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetPendingReservationsAtSource;

/**
 * Move what open orders still reserve at one source to another, keeping each order's ledger balanced
 */
class MoveSourceReservations
{
    /**
     * @param GetPendingReservationsAtSource $getPendingReservationsAtSource
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     * @param AppendReservationsInterface $appendReservations
     * @param ReservationBuilderInterface $reservationBuilder
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly GetPendingReservationsAtSource $getPendingReservationsAtSource,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock,
        private readonly AppendReservationsInterface $appendReservations,
        private readonly ReservationBuilderInterface $reservationBuilder,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Release every pending reservation of the SKUs at the origin and place it at the destination
     *
     * @param string[] $skus
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @return void
     * @throws ValidationException
     */
    public function execute(array $skus, string $originSourceCode, string $destinationSourceCode): void
    {
        $pending = $this->getPendingReservationsAtSource->execute($skus, $originSourceCode);
        if (!$pending) {
            return;
        }

        $this->assertDestinationServesEveryStock($pending, $originSourceCode, $destinationSourceCode);

        $reservations = [];
        foreach ($pending as $row) {
            $metadata = $this->serializer->serialize([
                'event_type' => 'source_transfer',
                'object_type' => 'order',
                'object_id' => $row['object_id'],
                'object_increment_id' => $row['object_increment_id'],
            ]);
            $reservations[] = $this->build($row, $originSourceCode, -$row['quantity'], $metadata);
            $reservations[] = $this->build($row, $destinationSourceCode, $row['quantity'], $metadata);
        }

        $this->appendReservations->execute($reservations);
    }

    /**
     * Refuse the move when the destination is not a source of a stock that holds the reservations
     *
     * @param array $pending
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @return void
     * @throws ValidationException
     */
    private function assertDestinationServesEveryStock(
        array $pending,
        string $originSourceCode,
        string $destinationSourceCode
    ): void {
        $servedStocks = [];
        foreach ($pending as $row) {
            $stockId = $row['stock_id'];
            if (!isset($servedStocks[$stockId])) {
                $servedStocks[$stockId] = $this->isAssigned($destinationSourceCode, $stockId);
            }
            if (!$servedStocks[$stockId]) {
                throw new ValidationException(
                    __(
                        'Source %destination is not assigned to stock %stock, which holds reservations at '
                        . '%origin for order %order.',
                        [
                            'destination' => $destinationSourceCode,
                            'stock' => $stockId,
                            'origin' => $originSourceCode,
                            'order' => $row['object_increment_id'],
                        ]
                    )
                );
            }
        }
    }

    /**
     * Whether the source is assigned to the stock
     *
     * @param string $sourceCode
     * @param int $stockId
     * @return bool
     */
    private function isAssigned(string $sourceCode, int $stockId): bool
    {
        foreach ($this->getSourcesAssignedToStock->execute($stockId) as $source) {
            if ($source->getSourceCode() === $sourceCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build one reservation row of the move
     *
     * @param array $row
     * @param string $sourceCode
     * @param float $quantity
     * @param string $metadata
     * @return ReservationInterface
     */
    private function build(array $row, string $sourceCode, float $quantity, string $metadata): ReservationInterface
    {
        return $this->reservationBuilder
            ->setStockId($row['stock_id'])
            ->setSku($row['sku'])
            ->setSourceCode($sourceCode)
            ->setQuantity($quantity)
            ->setObjectIncrementId($row['object_increment_id'])
            ->setMetadata($metadata)
            ->build();
    }
}
