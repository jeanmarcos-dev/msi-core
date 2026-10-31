<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;

/**
 * Drop the stock registry snapshot of the products whose source items were deleted.
 */
class DropStockRegistrySnapshotOnSourceItemsDeletePlugin extends AbstractDropStockRegistrySnapshotPlugin
{
    /**
     * Drop the snapshot after the source items are deleted.
     *
     * @param SourceItemsDeleteInterface $subject
     * @param void $result
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(SourceItemsDeleteInterface $subject, $result, array $sourceItems)
    {
        $this->dropSnapshot($sourceItems);

        return $result;
    }
}
