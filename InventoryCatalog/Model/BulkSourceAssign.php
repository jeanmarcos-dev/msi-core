<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalogApi\Api\BulkSourceAssignInterface;
use Magento\InventoryCatalogApi\Model\BulkSourceAssignValidatorInterface;
use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceAssign as BulkSourceAssignResource;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;

/**
 * @inheritdoc
 */
class BulkSourceAssign implements BulkSourceAssignInterface
{
    /**
     * @var BulkSourceAssignValidatorInterface
     */
    private $assignValidator;

    /**
     * @var BulkSourceAssignResource
     */
    private $bulkSourceAssign;

    /**
     * @var ReindexSkusInSources
     */
    private $reindexSkusInSources;

    /**
     * MassProductSourceAssign constructor.
     * @param BulkSourceAssignValidatorInterface $assignValidator
     * @param BulkSourceAssignResource $bulkSourceAssign
     * @param ReindexSkusInSources $reindexSkusInSources
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        BulkSourceAssignValidatorInterface $assignValidator,
        BulkSourceAssignResource $bulkSourceAssign,
        ReindexSkusInSources $reindexSkusInSources
    ) {
        $this->assignValidator = $assignValidator;
        $this->bulkSourceAssign = $bulkSourceAssign;
        $this->reindexSkusInSources = $reindexSkusInSources;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, array $sourceCodes): int
    {
        $validationResult = $this->assignValidator->validate($skus, $sourceCodes);
        if (!$validationResult->isValid()) {
            throw new ValidationException(__('Validation Failed'), null, 0, $validationResult);
        }

        $res = $this->bulkSourceAssign->execute($skus, $sourceCodes);
        $this->reindexSkusInSources->execute($skus, $sourceCodes);

        return $res;
    }
}
