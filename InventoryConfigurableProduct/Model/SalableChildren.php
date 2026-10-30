<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use WeakMap;

/**
 * Answer, for any number of configurable products at once, whether a child is salable in a store.
 */
class SalableChildren
{
    /**
     * @var WeakMap
     */
    private WeakMap $answers;

    /**
     * @param GetParentIdsWithSalableChildren $getParentIds
     */
    public function __construct(private readonly GetParentIdsWithSalableChildren $getParentIds)
    {
        $this->answers = new WeakMap();
    }

    /**
     * Resolve the parents that are not resolved yet for the store with a single query.
     *
     * @param ProductInterface[] $parents
     * @param int $storeId
     * @return void
     */
    public function preload(array $parents, int $storeId): void
    {
        $pending = array_values(array_filter(
            $parents,
            fn (ProductInterface $parent) => !isset($this->answers[$parent][$storeId])
        ));
        if (!$pending) {
            return;
        }

        $withChildren = array_flip($this->getParentIds->execute($pending, $storeId));
        foreach ($pending as $parent) {
            $answers = $this->answers[$parent] ?? [];
            $answers[$storeId] = isset($withChildren[(int)$parent->getId()]);
            $this->answers[$parent] = $answers;
        }
    }

    /**
     * Check whether the parent has a salable child in the store, resolving it as a batch of one if needed.
     *
     * @param ProductInterface $parent
     * @param int $storeId
     * @return bool
     */
    public function exist(ProductInterface $parent, int $storeId): bool
    {
        $this->preload([$parent], $storeId);

        return $this->answers[$parent][$storeId];
    }
}
