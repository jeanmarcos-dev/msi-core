<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryCatalog;

use Magento\CatalogInventory\Model\ResourceModel\QtyCounterInterface;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryCatalog\Plugin\CatalogInventory\UpdateSourceItemAtLegacyQtyCounterPlugin;

class LegacyQtyCounterContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record the legacy quantity counter as a legacy bridge change
     *
     * @param UpdateSourceItemAtLegacyQtyCounterPlugin $subject
     * @param callable $proceed
     * @param QtyCounterInterface $qtyCounter
     * @param callable $counterProceed
     * @param array $items
     * @param int|string $websiteId
     * @param string $operator
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAroundCorrectItemsQty(
        UpdateSourceItemAtLegacyQtyCounterPlugin $subject,
        callable $proceed,
        QtyCounterInterface $qtyCounter,
        callable $counterProceed,
        array $items,
        $websiteId,
        $operator
    ): void {
        $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::LegacyBridge),
            fn () => $proceed($qtyCounter, $counterProceed, $items, $websiteId, $operator)
        );
    }
}
