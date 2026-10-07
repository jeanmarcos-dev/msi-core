<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\InventoryCatalogApi\Api\BulkPartialInventoryTransferInterface;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;

/**
 * Serialise a partial inventory transfer with checkout on the (sku, source) locks of both sources.
 */
class LockSourceItemsOnBulkPartialInventoryTransferPlugin
{
    /**
     * @param AcquireStockItemLocks $acquireStockItemLocks
     */
    public function __construct(
        private readonly AcquireStockItemLocks $acquireStockItemLocks
    ) {
    }

    /**
     * Hold the origin and destination locks of the transferred SKUs until the transfer commits.
     *
     * @param BulkPartialInventoryTransferInterface $subject
     * @param callable $proceed
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @param PartialInventoryTransferItemInterface[] $items
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        BulkPartialInventoryTransferInterface $subject,
        callable $proceed,
        string $originSourceCode,
        string $destinationSourceCode,
        array $items
    ): void {
        $skus = [];
        foreach ($items as $item) {
            $skus[] = $item->getSku();
        }

        $this->acquireStockItemLocks->executeForSources($skus, [$originSourceCode, $destinationSourceCode]);
        try {
            $proceed($originSourceCode, $destinationSourceCode, $items);
        } finally {
            $this->acquireStockItemLocks->releaseAll();
        }
    }
}
