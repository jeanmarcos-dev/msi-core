<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryCatalog;

use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;
use Magento\InventoryAdjustment\Model\TransferMetadataFactory;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;

class RecordPartialTransferPlugin
{
    /**
     * @param AdjustmentRecorder $recorder
     * @param SourceItemKeys $sourceItemKeys
     * @param AdjustmentContextInterface $context
     * @param TransferMetadataFactory $transferMetadataFactory
     */
    public function __construct(
        private readonly AdjustmentRecorder $recorder,
        private readonly SourceItemKeys $sourceItemKeys,
        private readonly AdjustmentContextInterface $context,
        private readonly TransferMetadataFactory $transferMetadataFactory
    ) {
    }

    /**
     * Record both sources of a partial transfer
     *
     * @param TransferInventoryPartially $subject
     * @param callable $proceed
     * @param PartialInventoryTransferItemInterface $transfer
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        TransferInventoryPartially $subject,
        callable $proceed,
        PartialInventoryTransferItemInterface $transfer,
        string $originSourceCode,
        string $destinationSourceCode
    ): void {
        $this->context->run(
            $this->transferMetadataFactory->create(),
            fn () => $this->recorder->record(
                $this->sourceItemKeys->forSkusInSources(
                    [$transfer->getSku()],
                    [$originSourceCode, $destinationSourceCode]
                ),
                fn () => $proceed($transfer, $originSourceCode, $destinationSourceCode)
            )
        );
    }
}
