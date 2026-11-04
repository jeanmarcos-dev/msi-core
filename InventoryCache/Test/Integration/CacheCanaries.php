<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Test\Integration;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\PageCache\Model\Cache\Type as FullPageCache;
use Magento\TestFramework\Helper\Bootstrap;

class CacheCanaries
{
    private const PREFIX = 'msi_cache_canary_';
    private const BYSTANDER = self::PREFIX . 'bystander';
    private const BYSTANDER_TAG = 'msi_cache_canary';

    /**
     * @param int[] $productIds
     */
    public function __construct(private readonly array $productIds)
    {
    }

    public function plant(): void
    {
        foreach ($this->caches() as $cache) {
            $cache->save('1', self::BYSTANDER, [self::BYSTANDER_TAG]);
            foreach ($this->productIds as $productId) {
                $cache->save('1', self::PREFIX . $productId, [Product::CACHE_TAG . '_' . $productId]);
            }
        }
    }

    /**
     * @return array<string, int[]>
     */
    public function cleanedProductIds(): array
    {
        $cleaned = [];
        foreach ($this->caches() as $name => $cache) {
            $ids = [];
            foreach ($this->productIds as $productId) {
                if ($cache->load(self::PREFIX . $productId) === false) {
                    $ids[] = (int)$productId;
                }
            }
            sort($ids);
            $cleaned[$name] = $ids;
        }

        return $cleaned;
    }

    /**
     * @return array<string, bool>
     */
    public function bystanderKept(): array
    {
        $kept = [];
        foreach ($this->caches() as $name => $cache) {
            $kept[$name] = $cache->load(self::BYSTANDER) !== false;
        }

        return $kept;
    }

    /**
     * @return array<string, CacheInterface|FrontendInterface>
     */
    private function caches(): array
    {
        $objectManager = Bootstrap::getObjectManager();

        return [
            'app' => $objectManager->get(CacheInterface::class),
            'full_page' => $objectManager->get(FullPageCache::class),
        ];
    }
}
