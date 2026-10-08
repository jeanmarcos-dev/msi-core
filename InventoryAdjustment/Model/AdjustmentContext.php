<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;

class AdjustmentContext implements AdjustmentContextInterface, ResetAfterRequestInterface
{
    /**
     * @var AdjustmentMetadataInterface[]
     */
    private array $stack = [];

    /**
     * @inheritdoc
     */
    public function run(AdjustmentMetadataInterface $metadata, callable $operation): mixed
    {
        $this->stack[] = $metadata;
        try {
            return $operation();
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * @inheritdoc
     */
    public function getCurrent(): ?AdjustmentMetadataInterface
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->stack = [];
    }
}
