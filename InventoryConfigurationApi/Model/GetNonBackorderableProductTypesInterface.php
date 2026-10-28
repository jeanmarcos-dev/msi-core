<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurationApi\Model;

/**
 * @api
 */
interface GetNonBackorderableProductTypesInterface
{
    /**
     * Get the product types that do not admit backorders when they manage stock.
     *
     * @return string[]
     */
    public function execute(): array;
}
