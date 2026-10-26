<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Consume queued purge messages and flush the visualizer fragment for each SKU.
 *
 * Clearing the coalescing guard first reopens the window so writes that land after this run are
 * queued again, and the flush reads the live availability so the recomputed fragment reflects every
 * write coalesced up to this point. Purging is best-effort: a failure must not requeue forever, so
 * throwables are swallowed and logged.
 */
class PurgeConsumer
{
    /**
     * @param PurgeBySkus $purgeBySkus
     * @param CacheInterface $cache
     * @param GetEnabledStoreIds $getEnabledStoreIds
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PurgeBySkus $purgeBySkus,
        private readonly CacheInterface $cache,
        private readonly GetEnabledStoreIds $getEnabledStoreIds,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Reopen the coalescing guard of the SKU and purge its fragment while a store view shows the panel.
     *
     * @param string $sku
     * @return void
     */
    public function process(string $sku): void
    {
        try {
            $this->cache->remove(DispatchPurge::GUARD_PREFIX . $sku);
            if ($sku === '' || $this->getEnabledStoreIds->execute() === []) {
                return;
            }
            $this->purgeBySkus->execute([$sku]);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Stock visualizer cache purge failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
