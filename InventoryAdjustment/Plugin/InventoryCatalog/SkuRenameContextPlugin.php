<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryCatalog;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product\CreateSourceItemsPlugin;

class SkuRenameContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record the source items copied to a renamed SKU
     *
     * @param CreateSourceItemsPlugin $subject
     * @param callable $proceed
     * @param Product $productResource
     * @param Product $result
     * @param AbstractModel $product
     * @return Product
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAfterSave(
        CreateSourceItemsPlugin $subject,
        callable $proceed,
        Product $productResource,
        Product $result,
        AbstractModel $product
    ): Product {
        return $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::Other, note: 'sku_rename'),
            fn () => $proceed($productResource, $result, $product)
        );
    }
}
