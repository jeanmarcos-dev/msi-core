<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;

class TransferInventoryPartially
{
    /** @var ResourceConnection */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Transfers inventory between sources, updating quantities and legacy stock data for the given SKU and sources.
     *
     * @param PartialInventoryTransferItemInterface $transfer
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @throws ValidationException
     * @throws NoSuchEntityException
     */
    public function execute(
        PartialInventoryTransferItemInterface $transfer,
        string $originSourceCode,
        string $destinationSourceCode
    ): void {
        $sku = $transfer->getSku();
        $qty = (float)$transfer->getQty();
        if ($qty <= 0) {
            throw new ValidationException(
                __('Transfer quantity for sku %sku must be greater than zero', ['sku' => $sku])
            );
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $lockedSourceCodes = $this->lockSourceItems($sku, [$originSourceCode, $destinationSourceCode]);
            foreach ([$originSourceCode, $destinationSourceCode] as $sourceCode) {
                if (!in_array($sourceCode, $lockedSourceCodes, true)) {
                    throw new NoSuchEntityException(
                        __(
                            'Source item for %sku and %sourceCode does not exist',
                            ['sku' => $sku, 'sourceCode' => $sourceCode]
                        )
                    );
                }
            }

            $this->moveQuantity($sku, $qty, $originSourceCode, $destinationSourceCode);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Take the row locks of the SKU on the given sources, returning the sources that hold a source item.
     *
     * @param string $sku
     * @param string[] $sourceCodes
     * @return string[]
     */
    private function lockSourceItems(string $sku, array $sourceCodes): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM),
                [SourceItemInterface::SOURCE_CODE]
            )
            ->where(SourceItemInterface::SKU . ' = ?', $sku)
            ->where(SourceItemInterface::SOURCE_CODE . ' IN (?)', $sourceCodes)
            ->forUpdate(true);

        return $connection->fetchCol($select);
    }

    /**
     * Move the quantity with relative updates, refusing to take the origin below zero.
     *
     * @param string $sku
     * @param float $qty
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @return void
     * @throws ValidationException
     */
    private function moveQuantity(
        string $sku,
        float $qty,
        string $originSourceCode,
        string $destinationSourceCode
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM);

        $movedRows = $connection->update(
            $tableName,
            [SourceItemInterface::QUANTITY => new Expression($connection->quoteInto('quantity - ?', $qty))],
            [
                SourceItemInterface::SOURCE_CODE . '=?' => $originSourceCode,
                SourceItemInterface::SKU . '=?' => $sku,
                SourceItemInterface::QUANTITY . ' >= ?' => $qty,
            ]
        );
        if ($movedRows !== 1) {
            throw new ValidationException(
                __('Requested transfer amount for sku %sku is not available', ['sku' => $sku])
            );
        }

        $connection->update(
            $tableName,
            [
                SourceItemInterface::QUANTITY => new Expression($connection->quoteInto('quantity + ?', $qty)),
                SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
            ],
            [
                SourceItemInterface::SOURCE_CODE . '=?' => $destinationSourceCode,
                SourceItemInterface::SKU . '=?' => $sku,
            ]
        );
    }
}
