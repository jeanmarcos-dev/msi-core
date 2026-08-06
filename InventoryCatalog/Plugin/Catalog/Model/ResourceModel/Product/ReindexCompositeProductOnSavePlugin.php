<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryIndexer\Indexer\CompositeProductsIndexer;

/**
 * Index a composite product in every stock its children belong to, as soon as the links exist.
 *
 * A composite product carries no source item of its own, so the only thing that ever puts it in the
 * index is a reindex reaching it through its children. Saving the parent is what creates those links,
 * and by then the children have already been indexed without a parent to propagate to, which leaves
 * the parent unsalable until the next full reindex.
 */
class ReindexCompositeProductOnSavePlugin
{
    /**
     * @param CompositeProductsIndexer $compositeProductsIndexer
     */
    public function __construct(
        private readonly CompositeProductsIndexer $compositeProductsIndexer
    ) {
    }

    /**
     * Reindex the saved product when it is a composite one.
     *
     * @param ProductResource $subject
     * @param ProductResource $result
     * @param AbstractModel $product
     * @return ProductResource
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(
        ProductResource $subject,
        ProductResource $result,
        AbstractModel $product
    ): ProductResource {
        if (!$product instanceof Product || !$product->isComposite()) {
            return $result;
        }

        $this->compositeProductsIndexer->reindexList([(string) $product->getSku()]);

        // The parent may have just become salable, and the category listings it appears in filter on that.
        $product->setIsChangedCategories(true);
        $product->setAffectedCategoryIds($product->getCategoryIds());
        $product->cleanModelCache();

        return $result;
    }
}
