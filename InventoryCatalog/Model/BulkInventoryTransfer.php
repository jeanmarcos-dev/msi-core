<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer as BulkInventoryTransferResource;
use Magento\InventoryCatalogApi\Api\BulkInventoryTransferInterface;
use Magento\InventoryCatalogApi\Model\BulkInventoryTransferValidatorInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;

/**
 * @inheritdoc
 */
class BulkInventoryTransfer implements BulkInventoryTransferInterface
{
    /**
     * @var BulkInventoryTransferValidatorInterface
     */
    private $bulkInventoryTransferValidator;

    /**
     * @var BulkInventoryTransfer
     */
    private $bulkInventoryTransfer;

    /**
     * @var ReindexSkusInSources
     */
    private $reindexSkusInSources;

    /**
     * @param BulkInventoryTransferValidatorInterface $inventoryTransferValidator
     * @param BulkInventoryTransferResource $bulkInventoryTransfer
     * @param ReindexSkusInSources $reindexSkusInSources
     */
    public function __construct(
        BulkInventoryTransferValidatorInterface $inventoryTransferValidator,
        BulkInventoryTransferResource $bulkInventoryTransfer,
        ReindexSkusInSources $reindexSkusInSources
    ) {
        $this->bulkInventoryTransferValidator = $inventoryTransferValidator;
        $this->bulkInventoryTransfer = $bulkInventoryTransfer;
        $this->reindexSkusInSources = $reindexSkusInSources;
    }

    /**
     * @inheritdoc
     */
    public function execute(
        array $skus,
        string $originSource,
        string $destinationSource,
        bool $unassignFromOrigin
    ): bool {
        $validationResult = $this->bulkInventoryTransferValidator->validate(
            $skus,
            $originSource,
            $destinationSource
        );

        if (!$validationResult->isValid()) {
            throw new ValidationException(
                __('Validation Error: Select different sources for origin and destination.'),
                null,
                0,
                $validationResult
            );
        }

        $this->bulkInventoryTransfer->execute(
            $skus,
            $originSource,
            $destinationSource,
            $unassignFromOrigin
        );

        $this->reindexSkusInSources->execute($skus, [$originSource, $destinationSource]);

        return true;
    }
}
