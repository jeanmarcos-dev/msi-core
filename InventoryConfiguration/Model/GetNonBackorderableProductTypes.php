<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\InventoryConfigurationApi\Model\GetNonBackorderableProductTypesInterface;

class GetNonBackorderableProductTypes implements GetNonBackorderableProductTypesInterface
{
    /**
     * @param array $types
     */
    public function __construct(
        private readonly array $types = []
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): array
    {
        return array_values(array_filter($this->types));
    }
}
