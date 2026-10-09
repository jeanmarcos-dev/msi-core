<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryApi;

use Magento\InventoryAdjustment\Model\ExplicitAdjustmentResolver;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class ExplicitAdjustmentPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     * @param ExplicitAdjustmentResolver $resolver
     */
    public function __construct(
        private readonly AdjustmentContextInterface $context,
        private readonly ExplicitAdjustmentResolver $resolver
    ) {
    }

    /**
     * Record a source item save under the adjustment its caller sent
     *
     * @param SourceItemsSaveInterface $subject
     * @param callable $proceed
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(SourceItemsSaveInterface $subject, callable $proceed, array $sourceItems): void
    {
        $metadata = $this->resolver->resolve(array_map(
            fn (SourceItemInterface $sourceItem) => $sourceItem->getExtensionAttributes()?->getAdjustment(),
            $sourceItems
        ));
        if ($metadata === null) {
            $proceed($sourceItems);
            return;
        }
        $this->context->run($metadata, fn () => $proceed($sourceItems));
    }
}
