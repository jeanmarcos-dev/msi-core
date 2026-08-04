<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model\StockItemConfiguration;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Per-request cache of stock item configuration entities.
 */
class CacheStorage implements ResetAfterRequestInterface
{
    /**
     * @var StockItemInterface[]
     */
    private $cachedItems = [];

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->cachedItems = [];
    }

    /**
     * Save item to cache
     *
     * @param string $sku
     * @param StockItemInterface $item
     * @return void
     */
    public function set(string $sku, StockItemInterface $item): void
    {
        $this->cachedItems[$sku] = $item;
    }

    /**
     * Get item from cache
     *
     * @param string $sku
     * @return StockItemInterface|null
     */
    public function get(string $sku): ?StockItemInterface
    {
        return $this->cachedItems[$sku] ?? null;
    }

    /**
     * Delete item from cache
     *
     * @param string $sku
     * @return void
     */
    public function delete(string $sku): void
    {
        unset($this->cachedItems[$sku]);
    }
}
