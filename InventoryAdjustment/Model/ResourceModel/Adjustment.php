<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInterface;

class Adjustment extends AbstractDb
{
    public const TABLE_NAME = 'inventory_source_item_adjustment';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, AdjustmentInterface::ADJUSTMENT_ID);
    }
}
