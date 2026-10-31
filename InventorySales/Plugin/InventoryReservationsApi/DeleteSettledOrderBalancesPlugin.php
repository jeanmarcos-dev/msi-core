<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryReservationsApi;

use Magento\InventoryReservationsApi\Model\CleanupReservationsInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;

class DeleteSettledOrderBalancesPlugin
{
    /**
     * @param OrderBalanceGuard $orderBalanceGuard
     */
    public function __construct(
        private readonly OrderBalanceGuard $orderBalanceGuard
    ) {
    }

    /**
     * Delete the order balance rows that are back to zero once the compensated reservations are gone.
     *
     * @param CleanupReservationsInterface $subject
     * @param mixed $result
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(CleanupReservationsInterface $subject, $result): void
    {
        $this->orderBalanceGuard->deleteSettled();
    }
}
