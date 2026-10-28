<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryReservationsApi;

use Magento\InventoryReservationsApi\Model\CleanupReservationsInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;
use Magento\InventorySales\Plugin\InventoryReservationsApi\DeleteSettledOrderBalancesPlugin;
use PHPUnit\Framework\TestCase;

class DeleteSettledOrderBalancesPluginTest extends TestCase
{
    public function testDeletesTheSettledBalancesAfterTheReservationCleanup(): void
    {
        $guard = $this->createMock(OrderBalanceGuard::class);
        $guard->expects(self::once())->method('deleteSettled')->willReturn(3);

        (new DeleteSettledOrderBalancesPlugin($guard))->afterExecute(
            $this->createStub(CleanupReservationsInterface::class),
            null
        );
    }
}
