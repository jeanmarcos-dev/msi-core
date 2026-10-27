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
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionItemInterface;
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
     * @param GetManagedItemsToDeductFromShipment $getManagedItemsToDeductFromShipment
     * @param ItemRequestInterfaceFactory $itemRequestFactory
     * @param GetInventoryRequestFromOrder $getInventoryRequestFromOrder
     * @param SourceSelectionServiceInterface $sourceSelectionService
     * @param GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock,
        private readonly GetManagedItemsToDeductFromShipment $getManagedItemsToDeductFromShipment,
        private readonly ItemRequestInterfaceFactory $itemRequestFactory,
        private readonly GetInventoryRequestFromOrder $getInventoryRequestFromOrder,
        private readonly SourceSelectionServiceInterface $sourceSelectionService,
        private readonly GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
    ) {
    }

    /**
     * Return the single source that can ship every item with managed stock, or null when none can
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

        $itemsToDeduct = $this->getManagedItemsToDeductFromShipment->execute($shipment, $stockId);
        if (!$itemsToDeduct) {
            return (string)$sources[0]->getSourceCode();
        }

        return $this->selectSingleSource((int)$order->getId(), $sources, $itemsToDeduct);
    }

    /**
     * Return the source the default selection ships everything from, or else the first one holding every item
     *
     * @param int $orderId
     * @param SourceInterface[] $sources
     * @param ItemToDeductInterface[] $itemsToDeduct
     * @return string|null
     */
    private function selectSingleSource(int $orderId, array $sources, array $itemsToDeduct): ?string
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
        if (count($sourceCodes) === 1) {
            return (string)array_key_first($sourceCodes);
        }

        return $this->findSourceHoldingEveryItem($sources, $result->getSourceSelectionItems(), $itemsToDeduct);
    }

    /**
     * The highest-priority source whose available quantity covers every item on its own
     *
     * @param SourceInterface[] $sources
     * @param SourceSelectionItemInterface[] $selectionItems
     * @param ItemToDeductInterface[] $itemsToDeduct
     * @return string|null
     */
    private function findSourceHoldingEveryItem(array $sources, array $selectionItems, array $itemsToDeduct): ?string
    {
        $availableBySource = [];
        foreach ($selectionItems as $selectionItem) {
            $sku = $this->normalizeSku($selectionItem->getSku());
            $availableBySource[$selectionItem->getSourceCode()][$sku] = $selectionItem->getQtyAvailable();
        }

        foreach ($sources as $source) {
            $available = $availableBySource[$source->getSourceCode()] ?? [];
            if ($this->holdsEveryItem($available, $itemsToDeduct)) {
                return (string)$source->getSourceCode();
            }
        }

        return null;
    }

    /**
     * Whether the available quantities cover each item, compared the way the source deduction compares them
     *
     * @param array $availableBySku
     * @param ItemToDeductInterface[] $itemsToDeduct
     * @return bool
     */
    private function holdsEveryItem(array $availableBySku, array $itemsToDeduct): bool
    {
        foreach ($itemsToDeduct as $itemToDeduct) {
            $available = $availableBySku[$this->normalizeSku($itemToDeduct->getSku())] ?? null;
            if ($available === null || $available - $itemToDeduct->getQty() < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize a SKU the way the default source selection does
     *
     * @param string $sku
     * @return string
     */
    private function normalizeSku(string $sku): string
    {
        return mb_convert_case(trim($sku), MB_CASE_LOWER, 'UTF-8');
    }
}
