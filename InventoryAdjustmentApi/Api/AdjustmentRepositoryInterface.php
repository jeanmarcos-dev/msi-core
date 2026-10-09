<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentSearchResultsInterface;

/**
 * @api
 */
interface AdjustmentRepositoryInterface
{
    /**
     * Read one page of the source item adjustment history
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\InventoryAdjustmentApi\Api\Data\AdjustmentSearchResultsInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AdjustmentSearchResultsInterface;
}
