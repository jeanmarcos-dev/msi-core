<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

class DefaultMetadataProvider
{
    /**
     * @param State $appState
     */
    public function __construct(private readonly State $appState)
    {
    }

    /**
     * Metadata for a change made without an explicit context
     *
     * @return AdjustmentMetadataInterface
     */
    public function get(): AdjustmentMetadataInterface
    {
        return new AdjustmentMetadata(
            $this->isAdminArea() ? AdjustmentReason::Correction : AdjustmentReason::Other
        );
    }

    /**
     * Whether the request runs in the admin area
     *
     * @return bool
     */
    private function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (LocalizedException) {
            return false;
        }
    }
}
