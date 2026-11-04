<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

/**
 * @inheritdoc
 */
class GetProductIdsBySkus implements GetProductIdsBySkusInterface
{
    /**
     * @var ProductResourceModel
     */
    private $productResource;

    /**
     * @param ProductResourceModel $productResource
     */
    public function __construct(
        ProductResourceModel $productResource
    ) {
        $this->productResource = $productResource;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        $idsByStoredSkus = [];
        foreach ($this->productResource->getProductsIdsBySkus($skus) as $storedSku => $id) {
            $idsByStoredSkus[mb_convert_case((string)$storedSku, MB_CASE_LOWER, 'UTF-8')] = $id;
        }

        $idsBySkus = [];
        $notFoundedSkus = [];
        foreach ($skus as $sku) {
            $storedSku = mb_convert_case((string)$sku, MB_CASE_LOWER, 'UTF-8');
            if (array_key_exists($storedSku, $idsByStoredSkus)) {
                $idsBySkus[$sku] = $idsByStoredSkus[$storedSku];
            } else {
                $notFoundedSkus[] = $sku;
            }
        }

        if (!empty($notFoundedSkus)) {
            throw new NoSuchEntityException(
                __('Following products with requested skus were not found: %1', implode(', ', $notFoundedSkus))
            );
        }

        return $idsBySkus;
    }
}
