<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;

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
 */
class InertStockIndexer implements ActionInterface, MviewActionInterface
{
    // phpcs:disable Magento2.CodeAnalysis.EmptyBlock -- doing nothing is the whole point of this class

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
