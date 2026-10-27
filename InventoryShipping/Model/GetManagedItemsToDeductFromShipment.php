<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Model;

use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\Sales\Model\Order\Shipment;

class GetManagedItemsToDeductFromShipment
{
    /**
     * @param GetItemsToDeductFromShipment $getItemsToDeductFromShipment
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     */
    public function __construct(
        private readonly GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        private readonly GetStockItemConfigurationInterface $getStockItemConfiguration
    ) {
    }

    /**
     * Items of the shipment that the source deduction will deduct, leaving out those whose stock is not managed.
     *
     * @param Shipment $shipment
     * @param int $stockId
     * @return ItemToDeductInterface[]
     */
    public function execute(Shipment $shipment, int $stockId): array
    {
        return array_values(array_filter(
            $this->getItemsToDeductFromShipment->execute($shipment),
            fn (ItemToDeductInterface $item): bool => $this->isManaged((string)$item->getSku(), $stockId)
        ));
    }

    /**
     * Whether the stock of the SKU is managed in the stock.
     *
     * @param string $sku
     * @param int $stockId
     * @return bool
     */
    private function isManaged(string $sku, int $stockId): bool
    {
        try {
            return $this->getStockItemConfiguration->execute($sku, $stockId)->isManageStock();
        } catch (SkuIsNotAssignedToStockException) {
            return true;
        }
    }
}
