<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Plugin\InventoryShippingAdminUi;

use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\SourceReservation\DistributeCompensationToSources;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\SourceSelectionDataProvider;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tell the source selection page which sources the order reserved each item at
 */
class AddAllocatedSourcesToSourceSelectionPlugin
{
    /**
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param DistributeCompensationToSources $distributeToSources
     */
    public function __construct(
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        private readonly DistributeCompensationToSources $distributeToSources
    ) {
    }

    /**
     * Add the allocated sources and quantities to every item of the page
     *
     * @param SourceSelectionDataProvider $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetData(SourceSelectionDataProvider $subject, array $result): array
    {
        if (!$this->sourceReservationsConfig->isEnabled()) {
            return $result;
        }

        foreach ($result as $orderId => $orderData) {
            if (empty($orderData['items'])) {
                continue;
            }
            $order = $this->orderRepository->get((int)$orderId);
            $websiteId = (int)$this->storeManager->getStore($order->getStoreId())->getWebsiteId();
            $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();
            $sourceNames = array_column($orderData['sourceCodes'] ?? [], 'label', 'value');

            foreach ($orderData['items'] as $index => $item) {
                $result[$orderId]['items'][$index]['allocatedSources'] = $this->getAllocatedSources(
                    (string)$item['sku'],
                    (float)$item['qtyToShip'],
                    $stockId,
                    (string)$order->getIncrementId(),
                    $sourceNames
                );
            }
        }

        return $result;
    }

    /**
     * Sources the order still reserves the item at, up to the quantity to ship
     *
     * @param string $sku
     * @param float $qty
     * @param int $stockId
     * @param string $incrementId
     * @param array $sourceNames
     * @return array
     */
    private function getAllocatedSources(
        string $sku,
        float $qty,
        int $stockId,
        string $incrementId,
        array $sourceNames
    ): array {
        $allocated = [];
        foreach ($this->distributeToSources->execute([$sku => $qty], $stockId, $incrementId)[$sku] ?? [] as $row) {
            if ($row['source_code'] === null) {
                continue;
            }
            $allocated[] = [
                'sourceCode' => $row['source_code'],
                'sourceName' => $sourceNames[$row['source_code']] ?? $row['source_code'],
                'qty' => $row['quantity'],
            ];
        }

        return $allocated;
    }
}
