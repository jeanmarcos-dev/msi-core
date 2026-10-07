<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryShipping;

use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetPendingSourceReservations;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;

/**
 * Ship from the source the order reserved its items at, when one source holds the reservation for every item
 */
class PreferAllocatedSourceOnResolveShipmentSourceCodePlugin
{
    private const FLOAT_EPSILON = 0.000001;

    /**
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param GetItemsToDeductFromShipment $getItemsToDeductFromShipment
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param GetPendingSourceReservations $getPendingSourceReservations
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     */
    public function __construct(
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        private readonly GetPendingSourceReservations $getPendingSourceReservations,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
    ) {
    }

    /**
     * Return the highest-priority source whose pending reservations cover the whole shipment
     *
     * @param ResolveShipmentSourceCode $subject
     * @param callable $proceed
     * @param Shipment $shipment
     * @param Order $order
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        ResolveShipmentSourceCode $subject,
        callable $proceed,
        Shipment $shipment,
        Order $order
    ): ?string {
        if (!$this->sourceReservationsConfig->isEnabled()) {
            return $proceed($shipment, $order);
        }

        $qtyBySku = [];
        foreach ($this->getItemsToDeductFromShipment->execute($shipment) as $itemToDeduct) {
            $qtyBySku[$itemToDeduct->getSku()] = $itemToDeduct->getQty();
        }
        if (!$qtyBySku) {
            return $proceed($shipment, $order);
        }

        $stockId = (int)$this->stockByWebsiteIdResolver
            ->execute((int)$order->getStore()->getWebsiteId())
            ->getStockId();
        $pending = $this->getPendingSourceReservations->execute(
            (string)$order->getIncrementId(),
            array_map('strval', array_keys($qtyBySku)),
            $stockId
        );

        foreach ($this->getSourcesAssignedToStock->execute($stockId) as $source) {
            if ($this->coversEveryItem((string)$source->getSourceCode(), $qtyBySku, $pending)) {
                return (string)$source->getSourceCode();
            }
        }

        return $proceed($shipment, $order);
    }

    /**
     * Whether the order still reserves at least the shipped quantity of every item at the source
     *
     * @param string $sourceCode
     * @param array $qtyBySku
     * @param array $pending
     * @return bool
     */
    private function coversEveryItem(string $sourceCode, array $qtyBySku, array $pending): bool
    {
        foreach ($qtyBySku as $sku => $qty) {
            $reserved = -($pending[(string)$sku][$sourceCode] ?? 0.0);
            if ($reserved + self::FLOAT_EPSILON < $qty) {
                return false;
            }
        }

        return true;
    }
}
