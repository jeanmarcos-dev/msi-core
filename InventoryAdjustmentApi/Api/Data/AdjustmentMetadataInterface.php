<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Api\Data;

use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

/**
 * @api
 */
interface AdjustmentMetadataInterface
{
    /**
     * Reason recorded for the change
     *
     * @return AdjustmentReason
     */
    public function getReason(): AdjustmentReason;

    /**
     * Type of the document that caused the change
     *
     * @return string|null
     */
    public function getReferenceType(): ?string;

    /**
     * Id of the document that caused the change
     *
     * @return string|null
     */
    public function getReferenceId(): ?string;

    /**
     * Correlation id shared by the rows of one request
     *
     * @return string|null
     */
    public function getRequestId(): ?string;

    /**
     * Free text attached to the change
     *
     * @return string|null
     */
    public function getNote(): ?string;
}
