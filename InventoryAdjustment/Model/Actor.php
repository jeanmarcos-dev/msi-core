<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\InventoryAdjustmentApi\Model\ActorType;

class Actor
{
    /**
     * @param ActorType $type
     * @param string|null $id
     * @param string|null $label
     */
    public function __construct(
        public readonly ActorType $type,
        public readonly ?string $id = null,
        public readonly ?string $label = null
    ) {
    }
}
