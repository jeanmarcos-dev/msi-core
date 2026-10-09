<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

class TransferMetadataFactory
{
    /**
     * @param AdjustmentOrigin $origin
     */
    public function __construct(private readonly AdjustmentOrigin $origin)
    {
    }

    /**
     * Metadata of a transfer between two sources
     *
     * @return AdjustmentMetadataInterface
     */
    public function create(): AdjustmentMetadataInterface
    {
        return new AdjustmentMetadata(
            AdjustmentReason::TransferOut,
            'transfer',
            $this->origin->getRequestId(),
            inboundReason: AdjustmentReason::TransferIn
        );
    }
}
