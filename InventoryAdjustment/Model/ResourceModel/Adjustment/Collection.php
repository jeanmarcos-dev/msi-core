<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel\Adjustment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\InventoryAdjustment\Model\Adjustment;
use Magento\InventoryAdjustment\Model\ResourceModel\Adjustment as AdjustmentResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Adjustment::class, AdjustmentResource::class);
    }
}
