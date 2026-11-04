<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Test\Integration;

use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\TestFramework\Helper\Bootstrap;

class IndexedSalability
{
    /**
     * @param string[] $skus
     * @return array<string,bool>
     */
    public function onBaseWebsite(array $skus): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $stockId = (int)$objectManager->get(StockResolverInterface::class)
            ->execute(SalesChannelInterface::TYPE_WEBSITE, 'base')
            ->getStockId();
        $rows = $objectManager->get(GetStockItemsDataInterface::class)->execute($skus, $stockId);

        $salability = [];
        foreach ($skus as $sku) {
            $salability[$sku] = (bool)($rows[$sku][GetStockItemsDataInterface::IS_SALABLE] ?? false);
        }

        return $salability;
    }
}
