<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Api;

use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;

/**
 * @api
 */
interface AdjustmentContextInterface
{
    /**
     * Run an operation whose source item changes are recorded with the given metadata
     *
     * @param AdjustmentMetadataInterface $metadata
     * @param callable $operation
     * @return mixed
     */
    public function run(AdjustmentMetadataInterface $metadata, callable $operation): mixed;

    /**
     * Metadata of the innermost running operation
     *
     * @return AdjustmentMetadataInterface|null
     */
    public function getCurrent(): ?AdjustmentMetadataInterface;
}
