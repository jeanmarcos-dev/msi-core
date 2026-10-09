<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryCatalog;

use Magento\CatalogInventory\Model\Stock\Item;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryCatalog\Model\UpdateSourceItemBasedOnLegacyStockItem;

class LegacyStockItemContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record a legacy stock item save as a legacy bridge change
     *
     * @param UpdateSourceItemBasedOnLegacyStockItem $subject
     * @param callable $proceed
     * @param Item $legacyStockItem
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        UpdateSourceItemBasedOnLegacyStockItem $subject,
        callable $proceed,
        Item $legacyStockItem
    ): bool {
        return $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::LegacyBridge),
            fn () => $proceed($legacyStockItem)
        );
    }
}
