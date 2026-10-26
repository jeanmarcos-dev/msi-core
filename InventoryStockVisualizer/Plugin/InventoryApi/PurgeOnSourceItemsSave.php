<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Plugin\InventoryApi;

use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryStockVisualizer\Model\Cache\DispatchPurge;
use Magento\InventoryStockVisualizer\Model\Cache\GetEnabledStoreIds;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveSkusToPurge;
use Magento\InventoryStockVisualizer\Model\Cache\SnapshotSourceItemQty;
use Magento\InventoryStockVisualizer\Model\Cache\SourceItemDeltaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Purge the visualizer fragment for every SKU whose source quantity changed on save.
 *
 * This is the supply seam: it covers all stock writes that funnel through the save service (admin
 * grid, CSV import, REST) and reacts to plain quantity changes that never flip salability, which the
 * index-driven salability processor misses. The old quantity is snapshotted before the write so the
 * after-plugin can compute the exact per-source delta and let the shared decider judge the level
 * change. Purging is best-effort: a failure must never break the stock write.
 */
class PurgeOnSourceItemsSave
{
    /**
     * @var array<int, array<string, float>>
     */
    private array $snapshots = [];

    /**
     * @param GetEnabledStoreIds $getEnabledStoreIds
     * @param SnapshotSourceItemQty $snapshotSourceItemQty
     * @param SourceItemDeltaBuilder $sourceItemDeltaBuilder
     * @param ResolveSkusToPurge $resolveSkusToPurge
     * @param DispatchPurge $dispatchPurge
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GetEnabledStoreIds $getEnabledStoreIds,
        private readonly SnapshotSourceItemQty $snapshotSourceItemQty,
        private readonly SourceItemDeltaBuilder $sourceItemDeltaBuilder,
        private readonly ResolveSkusToPurge $resolveSkusToPurge,
        private readonly DispatchPurge $dispatchPurge,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Snapshot the current per-source quantity before the write.
     *
     * @param SourceItemsSaveInterface $subject
     * @param \Magento\InventoryApi\Api\Data\SourceItemInterface[] $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeExecute(SourceItemsSaveInterface $subject, array $sourceItems)
    {
        $snapshot = [];
        try {
            if ($this->getEnabledStoreIds->execute() !== []) {
                $snapshot = $this->snapshotSourceItemQty->execute($sourceItems);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Stock visualizer cache purge failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        $this->snapshots[] = $snapshot;
    }

    /**
     * Purge the fragment for every SKU whose source quantity moved the displayed value.
     *
     * @param SourceItemsSaveInterface $subject
     * @param mixed $result
     * @param \Magento\InventoryApi\Api\Data\SourceItemInterface[] $sourceItems
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(SourceItemsSaveInterface $subject, $result, array $sourceItems)
    {
        $snapshot = array_pop($this->snapshots) ?? [];
        try {
            if ($this->getEnabledStoreIds->execute() !== []) {
                $deltas = $this->sourceItemDeltaBuilder->build($sourceItems, $snapshot);
                if ($deltas) {
                    $this->dispatchPurge->execute($this->resolveSkusToPurge->execute($deltas));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Stock visualizer cache purge failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $result;
    }
}
