<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\CatalogInventory\Api\Data\StockItemCollectionInterface;
use Magento\Framework\Api\SearchResults;

/**
 * A stock item collection that carries items it was handed rather than a query to fetch them.
 *
 * The collection the repository builds resolves its items out of cataloginventory_stock_item the moment
 * they are read. Answering with this one instead lets a caller that reads through MSI skip that query
 * altogether, which is the whole point of serving the legacy registry from the index.
 */
class StockItemCollection extends SearchResults implements StockItemCollectionInterface
{
}
