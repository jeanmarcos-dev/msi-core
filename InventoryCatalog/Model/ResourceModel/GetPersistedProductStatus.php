<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\Store;

class GetPersistedProductStatus
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param MetadataPool $metadataPool
     * @param EavConfig $eavConfig
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * Get the status persisted for the scope the product is written to.
     *
     * @param Product $product
     * @return int|null
     */
    public function execute(Product $product): ?int
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, ProductInterface::STATUS);
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($attribute->getBackendTable(), ['value'])
            ->where($linkField . ' = ?', $product->getData($linkField))
            ->where('attribute_id = ?', (int) $attribute->getAttributeId())
            ->where('store_id IN (?)', [Store::DEFAULT_STORE_ID, (int) $product->getStoreId()])
            ->order('store_id DESC')
            ->limit(1);
        $value = $connection->fetchOne($select);

        return $value === false || $value === null ? null : (int) $value;
    }
}
