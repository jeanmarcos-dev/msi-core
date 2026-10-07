<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Framework\App\ResourceConnection;
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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param PartialInventoryTransferValidatorInterface $partialInventoryTransferValidator
     * @param TransferInventoryPartially $transferInventoryPartiallyCommand
     * @param ReindexSkusInSources $reindexSkusInSources
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        PartialInventoryTransferValidatorInterface $partialInventoryTransferValidator,
        TransferInventoryPartially $transferInventoryPartiallyCommand,
        ReindexSkusInSources $reindexSkusInSources,
        ResourceConnection $resourceConnection
    ) {
        $this->transferValidator = $partialInventoryTransferValidator;
        $this->transferCommand = $transferInventoryPartiallyCommand;
        $this->reindexSkusInSources = $reindexSkusInSources;
        $this->resourceConnection = $resourceConnection;
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
     * Transfer source items, all of them or none.
     *
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @param PartialInventoryTransferItemInterface[] $items
     * @throws ValidationException
     * @throws NoSuchEntityException
     */
    private function processTransfer(string $originSourceCode, string $destinationSourceCode, array $items): void
    {
        $connection = $this->resourceConnection->getConnection();
        $skus = [];
        $connection->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->transferCommand->execute($item, $originSourceCode, $destinationSourceCode);
                $skus[] = $item->getSku();
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->reindexSkusInSources->execute($skus, [$originSourceCode, $destinationSourceCode]);
    }
}
