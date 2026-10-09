<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\InventoryAdjustment\Model\ResourceModel\Adjustment as AdjustmentResource;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentExtensionInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInterface;

class Adjustment extends AbstractExtensibleModel implements AdjustmentInterface
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(AdjustmentResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getAdjustmentId(): ?int
    {
        return $this->getIntValue(self::ADJUSTMENT_ID);
    }

    /**
     * @inheritdoc
     */
    public function getSourceCode(): ?string
    {
        return $this->getStringValue(self::SOURCE_CODE);
    }

    /**
     * @inheritdoc
     */
    public function getSku(): ?string
    {
        return $this->getStringValue(self::SKU);
    }

    /**
     * @inheritdoc
     */
    public function getState(): ?string
    {
        return $this->getStringValue(self::STATE);
    }

    /**
     * @inheritdoc
     */
    public function getDelta(): ?float
    {
        return $this->getFloatValue(self::DELTA);
    }

    /**
     * @inheritdoc
     */
    public function getQuantityAfter(): ?float
    {
        return $this->getFloatValue(self::QUANTITY_AFTER);
    }

    /**
     * @inheritdoc
     */
    public function getStatusBefore(): ?int
    {
        return $this->getIntValue(self::STATUS_BEFORE);
    }

    /**
     * @inheritdoc
     */
    public function getStatusAfter(): ?int
    {
        return $this->getIntValue(self::STATUS_AFTER);
    }

    /**
     * @inheritdoc
     */
    public function getReason(): ?string
    {
        return $this->getStringValue(self::REASON);
    }

    /**
     * @inheritdoc
     */
    public function getActorType(): ?string
    {
        return $this->getStringValue(self::ACTOR_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function getActorId(): ?string
    {
        return $this->getStringValue(self::ACTOR_ID);
    }

    /**
     * @inheritdoc
     */
    public function getActorLabel(): ?string
    {
        return $this->getStringValue(self::ACTOR_LABEL);
    }

    /**
     * @inheritdoc
     */
    public function getReferenceType(): ?string
    {
        return $this->getStringValue(self::REFERENCE_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function getReferenceId(): ?string
    {
        return $this->getStringValue(self::REFERENCE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getRequestId(): ?string
    {
        return $this->getStringValue(self::REQUEST_ID);
    }

    /**
     * @inheritdoc
     */
    public function getNote(): ?string
    {
        return $this->getStringValue(self::NOTE);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getStringValue(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes(): ?AdjustmentExtensionInterface
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(AdjustmentExtensionInterface $extensionAttributes): void
    {
        $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Stored value as an integer
     *
     * @param string $key
     * @return int|null
     */
    private function getIntValue(string $key): ?int
    {
        $value = $this->getData($key);
        return $value === null ? null : (int)$value;
    }

    /**
     * Stored value as a float
     *
     * @param string $key
     * @return float|null
     */
    private function getFloatValue(string $key): ?float
    {
        $value = $this->getData($key);
        return $value === null ? null : (float)$value;
    }

    /**
     * Stored value as a string
     *
     * @param string $key
     * @return string|null
     */
    private function getStringValue(string $key): ?string
    {
        $value = $this->getData($key);
        return $value === null ? null : (string)$value;
    }
}
