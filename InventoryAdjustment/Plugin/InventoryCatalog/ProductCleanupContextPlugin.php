<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryCatalog;

use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryCatalog\Model\DeleteSourceItemsBySkus;

class ProductCleanupContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record the source items removed with their deleted products
     *
     * @param DeleteSourceItemsBySkus $subject
     * @param callable $proceed
     * @param array $skus
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(DeleteSourceItemsBySkus $subject, callable $proceed, array $skus): void
    {
        $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::Other, note: 'product_deleted'),
            fn () => $proceed($skus)
        );
    }
}
