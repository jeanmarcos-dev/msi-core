<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\Indexer;

use Magento\CatalogInventory\Model\Indexer\Stock as LegacyStockIndexer;

/**
 * Inert replacement for the legacy CatalogInventory stock indexer.
 *
 * Every consumer of cataloginventory_stock_status is served from the MSI index, and nothing writes
 * cataloginventory_stock_item any more, so rebuilding the legacy index would only publish stale data.
 *
 * The indexer id stays registered on purpose: Magento_Elasticsearch declares catalogsearch_fulltext as
 * depending on it, and Magento\Framework\Indexer\AbstractProcessor throws when the id cannot be resolved.
 * Making the action inert neutralizes the full, list, row and mview entry points at once, so the calls
 * core still makes through the processor become harmless no-ops.
 *
 * It extends the class it stands in for because the di.xml preference names a concrete class, and
 * several consumers type-hint that class rather than the two action interfaces. The constructor takes
 * no arguments on purpose: every method is overridden, so the legacy row, rows and full actions the
 * parent would pull in are never reached and need not be built.
 */
class InertStockIndexer extends LegacyStockIndexer
{
    // phpcs:disable Magento2.CodeAnalysis.EmptyBlock -- doing nothing is the whole point of this class

    /**
     * @inheritdoc
     */
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function execute($ids)
    {
    }

    /**
     * @inheritdoc
     */
    public function executeFull()
    {
    }

    /**
     * @inheritdoc
     */
    public function executeList(array $ids)
    {
    }

    /**
     * @inheritdoc
     */
    public function executeRow($id)
    {
    }
}
