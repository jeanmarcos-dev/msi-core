<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class AdjustmentWriter
{
    public const TABLE = 'inventory_source_item_adjustment';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Append adjustment rows
     *
     * @param array $rows
     * @return void
     */
    public function write(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->resourceConnection->getConnection()->insertMultiple(
            $this->resourceConnection->getTableName(self::TABLE),
            $rows
        );
    }
}
