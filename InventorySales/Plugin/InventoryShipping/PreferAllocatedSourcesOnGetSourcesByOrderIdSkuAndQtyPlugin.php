<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryShipping;

use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\SourceReservation\DistributeCompensationToSources;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\GetSourcesByOrderIdSkuAndQty;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Suggest shipping each item from the sources the order reserved it at, before the physical source selection
 */
class PreferAllocatedSourcesOnGetSourcesByOrderIdSkuAndQtyPlugin
{
    private const FLOAT_EPSILON = 0.000001;

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
     * Put the allocated quantities on their sources and let the source selection place only the rest
     *
     * @param GetSourcesByOrderIdSkuAndQty $subject
     * @param callable $proceed
     * @param int $orderId
     * @param string $sku
     * @param float $qty
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        GetSourcesByOrderIdSkuAndQty $subject,
        callable $proceed,
        int $orderId,
        string $sku,
        float $qty
    ): array {
        if (!$this->sourceReservationsConfig->isEnabled()) {
            return $proceed($orderId, $sku, $qty);
        }

        $allocated = $this->getAllocatedQtyBySource($orderId, $sku, $qty);
        $rows = $proceed($orderId, $sku, $qty);
        $listedSourceCodes = array_column($rows, 'sourceCode');
        $allocated = array_intersect_key($allocated, array_flip($listedSourceCodes));
        if (!$allocated) {
            return $rows;
        }

        $deductions = $allocated;
        $remainder = $qty - array_sum($allocated);
        if ($remainder > self::FLOAT_EPSILON) {
            foreach ($proceed($orderId, $sku, $remainder) as $row) {
                $deductions[$row['sourceCode']] = ($deductions[$row['sourceCode']] ?? 0.0) + $row['qtyToDeduct'];
            }
        }

        return array_map(
            static function (array $row) use ($deductions): array {
                $row['qtyToDeduct'] = $deductions[$row['sourceCode']] ?? 0.0;
                return $row;
            },
            $rows
        );
    }

    /**
     * Quantities the order still reserves at each source for the SKU, up to the requested quantity
     *
     * @param int $orderId
     * @param string $sku
     * @param float $qty
     * @return array
     */
    private function getAllocatedQtyBySource(int $orderId, string $sku, float $qty): array
    {
        $order = $this->orderRepository->get($orderId);
        $websiteId = (int)$this->storeManager->getStore($order->getStoreId())->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();

        $allocated = [];
        $allocations = $this->distributeToSources->execute([$sku => $qty], $stockId, (string)$order->getIncrementId());
        foreach ($allocations[$sku] ?? [] as $allocation) {
            if ($allocation['source_code'] !== null) {
                $allocated[$allocation['source_code']] = $allocation['quantity'];
            }
        }

        return $allocated;
    }
}
