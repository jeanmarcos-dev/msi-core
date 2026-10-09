<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface AdjustmentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * History rows of the page
     *
     * @return \Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInterface[]
     */
    public function getItems();

    /**
     * Set the history rows of the page
     *
     * @param \Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInterface[] $items
     * @return void
     */
    public function setItems(array $items);
}
