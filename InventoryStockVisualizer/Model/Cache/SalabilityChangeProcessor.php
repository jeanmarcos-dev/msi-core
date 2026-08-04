<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\InventoryIndexer\Indexer\SourceItem\CompositeProductProcessorInterface;
use Magento\InventoryIndexer\Model\GetProductsIdsToProcess;
use Magento\InventoryStockVisualizer\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Purge the visualizer fragment for products whose salability changed on reindex.
 *
 * Covers the supply side (stock/source-item writes): when a product flips in or
 * out of stock the state-mode fragment must refresh. Pure quantity changes that
 * do not flip salability are left to the reservation path (demand) and the
 * optional TTL (exact mode).
 */
class SalabilityChangeProcessor implements CompositeProductProcessorInterface
{
    /**
     * @param Config $config
     * @param GetProductsIdsToProcess $getProductsIdsToProcess
     * @param FlushStockVisualizerCache $flushStockVisualizerCache
     * @param LoggerInterface $logger
     * @param int $sortOrder
     */
    public function __construct(
        private readonly Config $config,
        private readonly GetProductsIdsToProcess $getProductsIdsToProcess,
        private readonly FlushStockVisualizerCache $flushStockVisualizerCache,
        private readonly LoggerInterface $logger,
        private readonly int $sortOrder = 40
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array<int,array<string,mixed>> $saleableStatusesBeforeSync
     * @param array<int,array<string,mixed>> $saleableStatusesAfterSync
     */
    public function process(array $saleableStatusesBeforeSync, array $saleableStatusesAfterSync): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        try {
            $productIds = $this->getProductsIdsToProcess->execute(
                $saleableStatusesBeforeSync,
                $saleableStatusesAfterSync
            );
            if ($productIds) {
                $this->flushStockVisualizerCache->execute(array_map('intval', $productIds));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Stock visualizer cache purge failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
