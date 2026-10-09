<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\Api\SearchResults;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentSearchResultsInterface;

class AdjustmentSearchResults extends SearchResults implements AdjustmentSearchResultsInterface
{
}
