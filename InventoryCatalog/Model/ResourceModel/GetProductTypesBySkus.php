<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;

/**
 * @inheritdoc
 */
class GetProductTypesBySkus implements GetProductTypesBySkusInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        $connection = $this->resource->getConnection();
        $productTable = $this->resource->getTableName('catalog_product_entity');

        $select = $connection->select()
            ->from(
                $productTable,
                [ProductInterface::SKU, ProductInterface::TYPE_ID]
            )->where(
                ProductInterface::SKU . ' IN (?)',
                $skus
            );

        $typesByStoredSkus = [];
        foreach ($connection->fetchPairs($select) as $storedSku => $productType) {
            $typesByStoredSkus[mb_convert_case((string)$storedSku, MB_CASE_LOWER, 'UTF-8')] = (string)$productType;
        }

        $result = [];
        foreach ($skus as $sku) {
            $storedSku = mb_convert_case((string)$sku, MB_CASE_LOWER, 'UTF-8');
            if (array_key_exists($storedSku, $typesByStoredSkus)) {
                $result[$sku] = $typesByStoredSkus[$storedSku];
            }
        }

        return $result;
    }
}
