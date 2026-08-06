<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Relation;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryCatalogApi\Model\GetChildrenSkusOfParentSkusInterface;

/**
 * @inheritdoc
 */
class GetChildrenSkusOfParentSkus implements GetChildrenSkusOfParentSkusInterface
{
    /**
     * @param Relation $productRelationResource
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param ResourceConnection $resourceConnection
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly Relation $productRelationResource,
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        if (!$skus) {
            return [];
        }

        $parentIds = $this->getProductIdsBySkus->execute($skus);
        $childIdsOfParentIds = $this->getRelationsByParent(array_values($parentIds));
        $flatChildIds = array_merge([], ...$childIdsOfParentIds);
        $childSkus = $flatChildIds
            ? $this->getSkusByProductIds->execute(array_values(array_unique($flatChildIds)))
            : [];

        $childSkusOfParentSkus = [];
        foreach ($skus as $sku) {
            $parentId = $parentIds[$sku];
            $childSkusOfParentSkus[$sku] = array_map(
                fn ($childId) => $childSkus[$childId],
                $childIdsOfParentIds[$parentId] ?? []
            );
        }

        return $childSkusOfParentSkus;
    }

    /**
     * Map every given parent product id to the ids of its children.
     *
     * Product\Relation grew a method for this in 2.4.9 only, and this runs on every save of a
     * composite product, so the query is issued here rather than through an API the older lines
     * of the fork would fatal on.
     *
     * @param int[] $parentIds
     * @return array<int, int[]>
     */
    private function getRelationsByParent(array $parentIds): array
    {
        if (!$parentIds) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $select = $connection->select()
            ->from(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                ['cpe.entity_id']
            )->joinInner(
                ['relation' => $this->productRelationResource->getMainTable()],
                'relation.parent_id = cpe.' . $linkField,
                ['relation.child_id']
            )->where('cpe.entity_id IN (?)', $parentIds);

        $childIdsOfParentIds = [];
        foreach ($connection->fetchAll($select) as $row) {
            $childIdsOfParentIds[$row['entity_id']][] = $row['child_id'];
        }

        return $childIdsOfParentIds;
    }
}
