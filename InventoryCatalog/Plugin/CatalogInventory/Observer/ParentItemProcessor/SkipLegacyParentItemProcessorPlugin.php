<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\CatalogInventory\Observer\ParentItemProcessor;

use Closure;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Observer\ParentItemProcessorInterface;

/**
 * Keep the legacy parent item processing out of the way.
 *
 * Every implementation of the interface ends in ChangeParentStockStatus, which recomputes a composite's
 * stored stock status from its children and writes it to cataloginventory_stock_item. Here the stored
 * flag is the merchant's own switch and the index is what derives salability from the children, so
 * letting it run would overwrite an intent with a computed value - into a table nothing reads.
 *
 * Upstream this was skipped in multi source mode only, because there the MSI indexers already covered
 * every stock. They cover stock 1 too now, so the exception became the rule.
 */
class SkipLegacyParentItemProcessorPlugin
{
    /**
     * Never run the wrapped processor.
     *
     * @param ParentItemProcessorInterface $subject
     * @param Closure $proceed
     * @param ProductInterface $product
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcess(
        ParentItemProcessorInterface $subject,
        Closure $proceed,
        ProductInterface $product
    ): void {
    }
}
