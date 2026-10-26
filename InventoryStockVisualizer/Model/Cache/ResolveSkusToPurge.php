<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;

/**
 * Decide which SKUs actually need a fragment purge given the quantity deltas that just occurred.
 *
 * Shared by the demand path (reservations) and the supply path (source-item writes) so both
 * apply the same granularity: quantity display purges every touched SKU, while level display
 * purges only when the appended deltas moved the aggregate or a per-source level across a
 * threshold. The aggregate check reads the current salable quantity, which is index-based and
 * therefore best-effort under scheduled indexing; the per-source check is exact. A SKU is purged
 * when any store view that shows the panel would display it differently, judged with that store
 * view's display configuration.
 */
class ResolveSkusToPurge
{
    /**
     * @param GetEnabledStoreIds $getEnabledStoreIds
     * @param ResolveDisplayConfig $resolveDisplayConfig
     * @param LevelResolver $levelResolver
     * @param GetStockViewInterface $getStockView
     */
    public function __construct(
        private readonly GetEnabledStoreIds $getEnabledStoreIds,
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly LevelResolver $levelResolver,
        private readonly GetStockViewInterface $getStockView
    ) {
    }

    /**
     * Reduce the grouped deltas to the distinct SKUs whose displayed value changed.
     *
     * @param array<int,array<string,array{total:float,bySource:array<string,float>}>> $deltas
     * @return string[]
     */
    public function execute(array $deltas): array
    {
        $storeIds = $this->getEnabledStoreIds->execute();
        if ($storeIds === []) {
            return [];
        }

        $skus = [];
        foreach ($deltas as $stockId => $bySku) {
            foreach ($bySku as $sku => $delta) {
                $sku = (string) $sku;
                $displayConfigs = $this->distinctDisplayConfigs($sku, $storeIds);
                if ($this->anyShowsTheQuantity($displayConfigs)
                    || $this->levelChanged($sku, (int) $stockId, $displayConfigs, $delta)
                ) {
                    $skus[$sku] = true;
                }
            }
        }

        return array_keys($skus);
    }

    /**
     * The display configurations the SKU has across the given store views, without repeats.
     *
     * @param string $sku
     * @param int[] $storeIds
     * @return DisplayConfig[]
     */
    private function distinctDisplayConfigs(string $sku, array $storeIds): array
    {
        $displayConfigs = [];
        foreach ($storeIds as $storeId) {
            $displayConfig = $this->resolveDisplayConfig->forSku($sku, $storeId);
            $key = implode('|', [
                $displayConfig->getDisplayType(),
                $displayConfig->getLevelBasis(),
                $displayConfig->getLevelHigh(),
                $displayConfig->getLevelLow(),
                $displayConfig->getFullQty() ?? '',
            ]);
            $displayConfigs[$key] = $displayConfig;
        }

        return array_values($displayConfigs);
    }

    /**
     * Whether any of the display configurations shows the exact quantity.
     *
     * @param DisplayConfig[] $displayConfigs
     * @return bool
     */
    private function anyShowsTheQuantity(array $displayConfigs): bool
    {
        foreach ($displayConfigs as $displayConfig) {
            if (!$displayConfig->isLevel()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the appended deltas moved the aggregate or any per-source level under any configuration.
     *
     * @param string $sku
     * @param int $stockId
     * @param DisplayConfig[] $displayConfigs
     * @param array{total:float,bySource:array<string,float>} $delta
     * @return bool
     */
    private function levelChanged(string $sku, int $stockId, array $displayConfigs, array $delta): bool
    {
        try {
            $view = $this->getStockView->execute($sku, $stockId);
        } catch (\Throwable $e) {
            return false;
        }

        foreach ($displayConfigs as $displayConfig) {
            if ($this->crossesALevel($view, $displayConfig, $delta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the appended deltas moved the aggregate or any per-source level under one configuration.
     *
     * @param StockViewInterface $view
     * @param DisplayConfig $displayConfig
     * @param array{total:float,bySource:array<string,float>} $delta
     * @return bool
     */
    private function crossesALevel(StockViewInterface $view, DisplayConfig $displayConfig, array $delta): bool
    {
        $afterAgg = $view->getSalableQty();
        $beforeAgg = $afterAgg - $delta['total'];
        if ($this->levelResolver->resolve($beforeAgg, $displayConfig)
            !== $this->levelResolver->resolve($afterAgg, $displayConfig)
        ) {
            return true;
        }

        foreach ($view->getSources() as $source) {
            $sourceDelta = $delta['bySource'][$source->getSourceCode()] ?? 0.0;
            if ($sourceDelta === 0.0) {
                continue;
            }
            $afterSource = $source->getQty();
            $beforeSource = $afterSource - $sourceDelta;
            if ($this->levelResolver->resolve($beforeSource, $displayConfig)
                !== $this->levelResolver->resolve($afterSource, $displayConfig)
            ) {
                return true;
            }
        }

        return false;
    }
}
