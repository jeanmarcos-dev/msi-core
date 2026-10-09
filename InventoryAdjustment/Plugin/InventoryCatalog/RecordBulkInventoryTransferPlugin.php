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
use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer;

class RecordBulkInventoryTransferPlugin
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
     * Record both sources of a bulk transfer
     *
     * @param BulkInventoryTransfer $subject
     * @param callable $proceed
     * @param array $skus
     * @param string $originSource
     * @param string $destinationSource
     * @param bool $unassignFromOrigin
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        BulkInventoryTransfer $subject,
        callable $proceed,
        array $skus,
        string $originSource,
        string $destinationSource,
        bool $unassignFromOrigin
    ): void {
        $this->context->run(
            $this->transferMetadataFactory->create(),
            fn () => $this->recorder->record(
                $this->sourceItemKeys->forSkusInSources($skus, [$originSource, $destinationSource]),
                fn () => $proceed($skus, $originSource, $destinationSource, $unassignFromOrigin)
            )
        );
    }
}
