<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Api\Data;

/**
 * @api
 */
interface AdjustmentInputInterface
{
    /**
     * Reason code of the change, from the closed list of adjustment reasons
     *
     * @return string|null
     */
    public function getReason(): ?string;

    /**
     * Set the reason code of the change, from the closed list of adjustment reasons
     *
     * @param string|null $reason
     * @return void
     */
    public function setReason(?string $reason): void;

    /**
     * Free text attached to the change
     *
     * @return string|null
     */
    public function getNote(): ?string;

    /**
     * Set the free text attached to the change
     *
     * @param string|null $note
     * @return void
     */
    public function setNote(?string $note): void;

    /**
     * Type of the document behind the change
     *
     * @return string|null
     */
    public function getReferenceType(): ?string;

    /**
     * Set the type of the document behind the change
     *
     * @param string|null $referenceType
     * @return void
     */
    public function setReferenceType(?string $referenceType): void;

    /**
     * Id of the document behind the change
     *
     * @return string|null
     */
    public function getReferenceId(): ?string;

    /**
     * Set the id of the document behind the change
     *
     * @param string|null $referenceId
     * @return void
     */
    public function setReferenceId(?string $referenceId): void;
}
