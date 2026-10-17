<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventorySales;

use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Model\ReservationLock;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;

/**
 * Hold the inventory lock of the reserved SKUs while a sale checks quantities and writes its reservations.
 */
class LockReservedSkusPlugin
{
    /**
     * @param ReservationLock $reservationLock
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     */
    public function __construct(
        private readonly ReservationLock $reservationLock,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
    ) {
    }

    /**
     * Lock the SKUs the sale reserves around the quantity check and the reservation write.
     *
     * @param AppendReservations $subject
     * @param callable $proceed
     * @param int $websiteId
     * @param array $itemsBySku
     * @param mixed $order
     * @param array $itemsToSell
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundReserve(
        AppendReservations $subject,
        callable $proceed,
        $websiteId,
        $itemsBySku,
        $order,
        $itemsToSell
    ) {
        $stockId = (int)$this->stockByWebsiteIdResolver->execute((int)$websiteId)->getStockId();
        $skusWithDemand = $this->getSkusWithDemand($itemsToSell);
        $lockNames = $skusWithDemand ? $this->reservationLock->acquire($skusWithDemand, $stockId) : [];
        try {
            return $proceed($websiteId, $itemsBySku, $order, $itemsToSell);
        } finally {
            $this->reservationLock->release($lockNames);
        }
    }

    /**
     * SKUs whose reservations take stock away, which are the only ones a concurrent sale can oversell.
     *
     * @param ItemToSellInterface[] $itemsToSell
     * @return string[]
     */
    private function getSkusWithDemand(array $itemsToSell): array
    {
        $netBySku = [];
        foreach ($itemsToSell as $item) {
            $netBySku[$item->getSku()] = ($netBySku[$item->getSku()] ?? 0.0) + $item->getQuantity();
        }

        return array_map('strval', array_keys(array_filter($netBySku, static fn(float $net) => $net < 0)));
    }
}
