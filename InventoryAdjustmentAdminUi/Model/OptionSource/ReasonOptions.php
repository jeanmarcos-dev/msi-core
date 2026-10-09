<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Model\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

class ReasonOptions implements OptionSourceInterface
{
    /**
     * Every reason of the closed list
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return array_map(
            fn (AdjustmentReason $reason) => ['value' => $reason->value, 'label' => $reason->value],
            AdjustmentReason::cases()
        );
    }
}
