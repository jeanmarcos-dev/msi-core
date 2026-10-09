<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class GetBulkUser
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * User type and id that scheduled a bulk, or null when the bulk is unknown
     *
     * @param string $bulkUuid
     * @return array|null
     */
    public function execute(string $bulkUuid): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName('magento_bulk'), ['user_type', 'user_id'])
                ->where('uuid = ?', $bulkUuid)
        );
        if (!$row) {
            return null;
        }
        return [
            'user_type' => $row['user_type'] === null ? null : (int)$row['user_type'],
            'user_id' => (int)$row['user_id'],
        ];
    }
}
