<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

class NonBackorderableStockItemConfiguration extends StockItemConfiguration
{
    /**
     * @inheritdoc
     */
    public function getBackorders(): int
    {
        return self::BACKORDERS_NO;
    }

    /**
     * @inheritdoc
     */
    public function getMinQty(): float
    {
        return max(0.0, parent::getMinQty());
    }
}
