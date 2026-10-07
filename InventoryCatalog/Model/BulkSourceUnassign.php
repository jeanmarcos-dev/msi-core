<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalogApi\Api\BulkSourceUnassignInterface;
use Magento\InventoryCatalogApi\Model\BulkSourceUnassignValidatorInterface;
use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceUnassign as BulkSourceUnassignResource;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;

/**
 * @inheritdoc
 */
class BulkSourceUnassign implements BulkSourceUnassignInterface
{
    /**
     * @var BulkSourceUnassignValidatorInterface
     */
    private $unassignValidator;

    /**
     * @var BulkSourceUnassignResource
     */
    private $bulkSourceUnassign;

    /**
     * @var ReindexSkusInSources
     */
    private $reindexSkusInSources;

    /**
     * MassProductSourceAssign constructor.
     * @param BulkSourceUnassignValidatorInterface $unassignValidator
     * @param BulkSourceUnassignResource $bulkSourceUnassign
     * @param ReindexSkusInSources $reindexSkusInSources
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        BulkSourceUnassignValidatorInterface $unassignValidator,
        BulkSourceUnassignResource $bulkSourceUnassign,
        ReindexSkusInSources $reindexSkusInSources
    ) {
        $this->unassignValidator = $unassignValidator;
        $this->bulkSourceUnassign = $bulkSourceUnassign;
        $this->reindexSkusInSources = $reindexSkusInSources;
    }

    /**
     * @inheritdoc
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(array $skus, array $sourceCodes): int
    {
        $validationResult = $this->unassignValidator->validate($skus, $sourceCodes);
        if (!$validationResult->isValid()) {
            throw new ValidationException(__('Validation Failed'), null, 0, $validationResult);
        }

        $res = $this->bulkSourceUnassign->execute($skus, $sourceCodes);

        $this->reindexSkusInSources->execute($skus, $sourceCodes);

        return $res;
    }
}
