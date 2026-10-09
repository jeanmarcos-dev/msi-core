<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * @api
 */
interface AdjustmentInterface extends ExtensibleDataInterface
{
    public const ADJUSTMENT_ID = 'adjustment_id';
    public const SOURCE_CODE = 'source_code';
    public const SKU = 'sku';
    public const STATE = 'state';
    public const DELTA = 'delta';
    public const QUANTITY_AFTER = 'quantity_after';
    public const STATUS_BEFORE = 'status_before';
    public const STATUS_AFTER = 'status_after';
    public const REASON = 'reason';
    public const ACTOR_TYPE = 'actor_type';
    public const ACTOR_ID = 'actor_id';
    public const ACTOR_LABEL = 'actor_label';
    public const REFERENCE_TYPE = 'reference_type';
    public const REFERENCE_ID = 'reference_id';
    public const REQUEST_ID = 'request_id';
    public const NOTE = 'note';
    public const CREATED_AT = 'created_at';

    /**
     * Id of the history row
     *
     * @return int|null
     */
    public function getAdjustmentId(): ?int;

    /**
     * Source of the changed item
     *
     * @return string|null
     */
    public function getSourceCode(): ?string;

    /**
     * SKU of the changed item
     *
     * @return string|null
     */
    public function getSku(): ?string;

    /**
     * Inventory state the change applies to
     *
     * @return string|null
     */
    public function getState(): ?string;

    /**
     * Signed change of the quantity
     *
     * @return float|null
     */
    public function getDelta(): ?float;

    /**
     * Quantity left by the change
     *
     * @return float|null
     */
    public function getQuantityAfter(): ?float;

    /**
     * Stock status before the change, when it changed
     *
     * @return int|null
     */
    public function getStatusBefore(): ?int;

    /**
     * Stock status after the change, when it changed
     *
     * @return int|null
     */
    public function getStatusAfter(): ?int;

    /**
     * Reason code of the change
     *
     * @return string|null
     */
    public function getReason(): ?string;

    /**
     * Kind of actor that made the change
     *
     * @return string|null
     */
    public function getActorType(): ?string;

    /**
     * Id of the actor that made the change
     *
     * @return string|null
     */
    public function getActorId(): ?string;

    /**
     * Name of the actor when the change was made
     *
     * @return string|null
     */
    public function getActorLabel(): ?string;

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

    /**
     * When the change was recorded
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Extension attributes of the history row
     *
     * @return \Magento\InventoryAdjustmentApi\Api\Data\AdjustmentExtensionInterface|null
     */
    public function getExtensionAttributes(): ?AdjustmentExtensionInterface;

    /**
     * Set the extension attributes of the history row
     *
     * @param \Magento\InventoryAdjustmentApi\Api\Data\AdjustmentExtensionInterface $extensionAttributes
     * @return void
     */
    public function setExtensionAttributes(AdjustmentExtensionInterface $extensionAttributes): void;
}
