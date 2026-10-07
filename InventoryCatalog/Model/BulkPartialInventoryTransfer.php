<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\BulkPartialInventoryTransferInterface;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventoryCatalogApi\Model\PartialInventoryTransferValidatorInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;

class BulkPartialInventoryTransfer implements BulkPartialInventoryTransferInterface
{
    /**
     * @var PartialInventoryTransferValidatorInterface
     */
    private $transferValidator;

    /**
     * @var TransferInventoryPartially
     */
    private $transferCommand;

    /**
     * @var ReindexSkusInSources
     */
    private $reindexSkusInSources;

    /**
     * @param PartialInventoryTransferValidatorInterface $partialInventoryTransferValidator
     * @param TransferInventoryPartially $transferInventoryPartiallyCommand
     * @param ReindexSkusInSources $reindexSkusInSources
     */
    public function __construct(
        PartialInventoryTransferValidatorInterface $partialInventoryTransferValidator,
        TransferInventoryPartially $transferInventoryPartiallyCommand,
        ReindexSkusInSources $reindexSkusInSources
    ) {
        $this->transferValidator = $partialInventoryTransferValidator;
        $this->transferCommand = $transferInventoryPartiallyCommand;
        $this->reindexSkusInSources = $reindexSkusInSources;
    }

    /**
     * Run bulk partial inventory transfer for specified items.
     *
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @param PartialInventoryTransferItemInterface[] $items
     * @return void
     * @throws ValidationException
     * @throws NoSuchEntityException
     */
    public function execute(string $originSourceCode, string $destinationSourceCode, array $items): void
    {
        $validationResult = $this->transferValidator->validate($originSourceCode, $destinationSourceCode, $items);
        if (!$validationResult->isValid()) {
            throw new ValidationException(__("Transfer validation failed"), null, 0, $validationResult);
        }

        $this->processTransfer($originSourceCode, $destinationSourceCode, $items);
    }

    /**
     * Transfer source items.
     *
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @param PartialInventoryTransferItemInterface[] $items
     * @throws NoSuchEntityException
     */
    private function processTransfer(string $originSourceCode, string $destinationSourceCode, array $items): void
    {
        $skus = [];
        foreach ($items as $item) {
            $this->transferCommand->execute($item, $originSourceCode, $destinationSourceCode);
            $skus[] = $item->getSku();
        }

        $this->reindexSkusInSources->execute($skus, [$originSourceCode, $destinationSourceCode]);
    }
}
