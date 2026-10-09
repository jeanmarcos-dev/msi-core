<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection as DataCollection;
use Magento\Framework\Exception\InputException;
use Magento\InventoryAdjustment\Model\ResourceModel\Adjustment\CollectionFactory;
use Magento\InventoryAdjustmentApi\Api\AdjustmentRepositoryInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentSearchResultsInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentSearchResultsInterfaceFactory;

class AdjustmentRepository implements AdjustmentRepositoryInterface
{
    /**
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionFactory $collectionFactory
     * @param AdjustmentSearchResultsInterfaceFactory $searchResultsFactory
     * @param Config $config
     */
    public function __construct(
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly CollectionFactory $collectionFactory,
        private readonly AdjustmentSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AdjustmentSearchResultsInterface
    {
        $maxPageSize = $this->config->getMaxPageSize();
        $pageSize = $searchCriteria->getPageSize();
        if ($pageSize !== null && $pageSize > $maxPageSize) {
            throw new InputException(
                __('The page size can be at most %1 adjustment history rows.', $maxPageSize)
            );
        }
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        if (!$searchCriteria->getSortOrders()) {
            $collection->setOrder(AdjustmentInterface::ADJUSTMENT_ID, DataCollection::SORT_ORDER_DESC);
        }
        if ($pageSize === null) {
            $collection->setPageSize($maxPageSize);
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems(array_values($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setSearchCriteria($searchCriteria);

        return $searchResults;
    }
}
