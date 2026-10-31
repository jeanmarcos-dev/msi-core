<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\Product;

use Magento\Catalog\Model\Product;
use WeakMap;

class PersistedStatusStorage
{
    /**
     * @var WeakMap
     */
    private WeakMap $storage;

    /**
     * Create an empty storage.
     */
    public function __construct()
    {
        $this->storage = new WeakMap();
    }

    /**
     * Keep the persisted status of a product object.
     *
     * @param Product $product
     * @param int|null $status
     * @return void
     */
    public function set(Product $product, ?int $status): void
    {
        $this->storage[$product] = $status;
    }

    /**
     * Get the persisted status kept for a product object.
     *
     * @param Product $product
     * @return int|null
     */
    public function get(Product $product): ?int
    {
        return $this->storage[$product] ?? null;
    }

    /**
     * Forget the persisted status kept for a product object.
     *
     * @param Product $product
     * @return void
     */
    public function delete(Product $product): void
    {
        unset($this->storage[$product]);
    }
}
