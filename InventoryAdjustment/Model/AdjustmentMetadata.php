<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

class AdjustmentMetadata implements AdjustmentMetadataInterface
{
    /**
     * @param AdjustmentReason $reason
     * @param string|null $referenceType
     * @param string|null $referenceId
     * @param string|null $requestId
     * @param string|null $note
     * @param AdjustmentReason|null $inboundReason
     */
    public function __construct(
        private readonly AdjustmentReason $reason,
        private readonly ?string $referenceType = null,
        private readonly ?string $referenceId = null,
        private readonly ?string $requestId = null,
        private readonly ?string $note = null,
        private readonly ?AdjustmentReason $inboundReason = null
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getReason(): AdjustmentReason
    {
        return $this->reason;
    }

    /**
     * @inheritdoc
     */
    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    /**
     * @inheritdoc
     */
    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    /**
     * @inheritdoc
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @inheritdoc
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @inheritdoc
     */
    public function getInboundReason(): ?AdjustmentReason
    {
        return $this->inboundReason;
    }
}
