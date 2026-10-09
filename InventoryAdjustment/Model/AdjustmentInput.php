<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInputInterface;

class AdjustmentInput implements AdjustmentInputInterface
{
    /**
     * @var string|null
     */
    private ?string $reason = null;

    /**
     * @var string|null
     */
    private ?string $note = null;

    /**
     * @var string|null
     */
    private ?string $referenceType = null;

    /**
     * @var string|null
     */
    private ?string $referenceId = null;

    /**
     * @inheritdoc
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @inheritdoc
     */
    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
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
    public function setNote(?string $note): void
    {
        $this->note = $note;
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
    public function setReferenceType(?string $referenceType): void
    {
        $this->referenceType = $referenceType;
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
    public function setReferenceId(?string $referenceId): void
    {
        $this->referenceId = $referenceId;
    }
}
