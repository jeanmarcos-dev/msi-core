<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryConfigurationApi\Model\GetNonBackorderableProductTypesInterface;

class GetNonBackorderableTypeCondition
{
    /**
     * @param GetNonBackorderableProductTypesInterface $getNonBackorderableProductTypes
     */
    public function __construct(
        private readonly GetNonBackorderableProductTypesInterface $getNonBackorderableProductTypes
    ) {
    }

    /**
     * Get the condition that matches the product types without backorders, or null when there are none.
     *
     * @param AdapterInterface $connection
     * @return string|null
     */
    public function execute(AdapterInterface $connection): ?string
    {
        $types = $this->getNonBackorderableProductTypes->execute();

        return $types ? $connection->quoteInto('product.type_id IN (?)', $types) : null;
    }
}
