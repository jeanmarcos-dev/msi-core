<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;

/**
 * Whether an order with the given increment id has been saved.
 */
class OrderExistsByIncrementId
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Check whether sales_order holds a row with the increment id.
     *
     * @param string $incrementId
     * @return bool
     */
    public function execute(string $incrementId): bool
    {
        $connection = $this->resourceConnection->getConnection();

        return (bool)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('sales_order'), ['entity_id'])
                ->where('increment_id = ?', $incrementId)
                ->limit(1)
        );
    }
}
