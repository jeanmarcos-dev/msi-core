<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Model;

use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\InventorySourceSelectionApi\Model\GetInventoryRequestFromOrder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;

/**
 * Resolve the source a shipment without an explicit source ships from, within the stock of its order
 */
class ResolveShipmentSourceCode
{
    /**
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     * @param GetItemsToDeductFromShipment $getItemsToDeductFromShipment
     * @param ItemRequestInterfaceFactory $itemRequestFactory
     * @param GetInventoryRequestFromOrder $getInventoryRequestFromOrder
     * @param SourceSelectionServiceInterface $sourceSelectionService
     * @param GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock,
        private readonly GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        private readonly ItemRequestInterfaceFactory $itemRequestFactory,
        private readonly GetInventoryRequestFromOrder $getInventoryRequestFromOrder,
        private readonly SourceSelectionServiceInterface $sourceSelectionService,
        private readonly GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
    ) {
    }

    /**
     * Return the single source that can ship every item, or null when none can
     *
     * @param Shipment $shipment
     * @param Order $order
     * @return string|null
     */
    public function execute(Shipment $shipment, Order $order): ?string
    {
        $websiteId = (int)$order->getStore()->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();
        $sources = $this->getSourcesAssignedToStock->execute($stockId);
        if (!$sources) {
            return null;
        }
        if (count($sources) === 1) {
            return (string)$sources[0]->getSourceCode();
        }

        $itemsToDeduct = $this->getItemsToDeductFromShipment->execute($shipment);
        if (!$itemsToDeduct) {
            return (string)$sources[0]->getSourceCode();
        }

        return $this->selectSingleSource((int)$order->getId(), $itemsToDeduct);
    }

    /**
     * Run the default source selection and return its source when it ships everything from one source
     *
     * @param int $orderId
     * @param ItemToDeductInterface[] $itemsToDeduct
     * @return string|null
     */
    private function selectSingleSource(int $orderId, array $itemsToDeduct): ?string
    {
        $requestItems = [];
        foreach ($itemsToDeduct as $itemToDeduct) {
            $requestItems[] = $this->itemRequestFactory->create(
                ['sku' => $itemToDeduct->getSku(), 'qty' => $itemToDeduct->getQty()]
            );
        }

        $result = $this->sourceSelectionService->execute(
            $this->getInventoryRequestFromOrder->execute($orderId, $requestItems),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
        if (!$result->isShippable()) {
            return null;
        }

        $sourceCodes = [];
        foreach ($result->getSourceSelectionItems() as $selectionItem) {
            if ($selectionItem->getQtyToDeduct() > 0) {
                $sourceCodes[$selectionItem->getSourceCode()] = true;
            }
        }

        return count($sourceCodes) === 1 ? (string)array_key_first($sourceCodes) : null;
    }
}
