<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Model\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\InventoryAdjustmentApi\Model\ActorType;

class ActorTypeOptions implements OptionSourceInterface
{
    /**
     * Every kind of actor that can change the stock
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return array_map(
            fn (ActorType $type) => ['value' => $type->value, 'label' => $type->value],
            ActorType::cases()
        );
    }
}
