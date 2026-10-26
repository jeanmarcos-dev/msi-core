<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Controller;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\PageCache\Model\Config as PageCacheConfig;

/**
 * Shared builder for the stock visualizer AJAX fragments.
 *
 * Centralises the JSON result creation, the public/no-store cache headers, the dedicated
 * purge tags and the id resolution the fragment controllers all share, so each controller
 * carries only its own availability logic.
 */
class FragmentResponder
{
    /**
     * Default public lifetime when neither the feature nor the FPC define one.
     */
    private const DEFAULT_TTL = 86400;

    /**
     * @param JsonFactory $jsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $config
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param PageCacheConfig $pageCacheConfig
     */
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly PageCacheConfig $pageCacheConfig
    ) {
    }

    /**
     * A fresh JSON result.
     *
     * @return Json
     */
    public function create(): Json
    {
        return $this->jsonFactory->create();
    }

    /**
     * Set the payload and mark the response non-cacheable.
     *
     * @param Json $result
     * @param mixed $data
     * @return Json
     */
    public function uncacheable(Json $result, $data): Json
    {
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);

        return $result->setData(['data' => $data]);
    }

    /**
     * Set the payload and, while the page cache is on, add public headers and the purge tags of each id.
     *
     * @param Json $result
     * @param int[] $productIds
     * @param mixed $data
     * @return Json
     */
    public function cacheable(Json $result, array $productIds, $data): Json
    {
        if (!$this->pageCacheConfig->isEnabled()) {
            return $this->uncacheable($result, $data);
        }

        $ttl = $this->config->getTtl() ?: (int) $this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TTL);
        $ttl = $ttl > 0 ? $ttl : self::DEFAULT_TTL;

        $tags = [];
        foreach ($productIds as $productId) {
            $tags[] = CacheTag::CACHE_TAG . '_' . (int) $productId;
            $tags[] = Product::CACHE_TAG . '_' . (int) $productId;
        }

        $result->setHeader('X-Magento-Tags', implode(',', $tags), true);
        $result->setHeader('Cache-Control', 'public, max-age=' . $ttl . ', s-maxage=' . $ttl, true);
        $result->setHeader('Pragma', 'cache', true);

        return $result->setData(['data' => $data]);
    }

    /**
     * Resolve the real product id for a single SKU, or 0 when it cannot be resolved.
     *
     * @param string $sku
     * @return int
     */
    public function resolveProductId(string $sku): int
    {
        $ids = $this->resolveProductIds([$sku]);

        return $ids[0] ?? 0;
    }

    /**
     * Resolve the real product ids for the given SKUs, or an empty array on failure.
     *
     * @param string[] $skus
     * @return int[]
     */
    public function resolveProductIds(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        try {
            $map = $this->getProductIdsBySkus->execute($skus);
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_map('intval', $map));
    }

    /**
     * Resolve a SKU from a product id (composite selection sends the chosen child id), or ''.
     *
     * @param int $productId
     * @return string
     */
    public function resolveSkuFromProductId(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        try {
            $skus = $this->getSkusByProductIds->execute([$productId]);
        } catch (\Throwable $e) {
            return '';
        }

        return (string) ($skus[$productId] ?? '');
    }
}
