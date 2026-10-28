<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\InventoryApi\Model\IsProductAssignedToStockInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\InventoryConfigurationApi\Model\GetNonBackorderableProductTypesInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;

/**
 * @inheritdoc
 */
class GetStockItemConfiguration implements GetStockItemConfigurationInterface
{
    /**
     * @var GetStockItemConfigurationBySku
     */
    private $getStockItemConfigurationBySku;

    /**
     * @var StockItemConfigurationFactory
     */
    private $stockItemConfigurationFactory;

    /**
     * @var IsProductAssignedToStockInterface
     */
    private $isProductAssignedToStock;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @var IsSourceItemManagementAllowedForSkuInterface
     */
    private $isSourceItemManagementAllowedForSku;

    /**
     * @param GetStockItemConfigurationBySku $getStockItemConfigurationBySku
     * @param StockItemConfigurationFactory $stockItemConfigurationFactory
     * @param IsProductAssignedToStockInterface $isProductAssignedToStock
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param GetNonBackorderableProductTypesInterface $getNonBackorderableProductTypes
     * @param NonBackorderableStockItemConfigurationFactory $nonBackorderableStockItemConfigurationFactory
     */
    public function __construct(
        GetStockItemConfigurationBySku $getStockItemConfigurationBySku,
        StockItemConfigurationFactory $stockItemConfigurationFactory,
        IsProductAssignedToStockInterface $isProductAssignedToStock,
        DefaultStockProviderInterface $defaultStockProvider,
        IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku,
        private readonly GetProductTypesBySkusInterface $getProductTypesBySkus,
        private readonly GetNonBackorderableProductTypesInterface $getNonBackorderableProductTypes,
        private readonly NonBackorderableStockItemConfigurationFactory $nonBackorderableStockItemConfigurationFactory
    ) {
        $this->getStockItemConfigurationBySku = $getStockItemConfigurationBySku;
        $this->stockItemConfigurationFactory = $stockItemConfigurationFactory;
        $this->isProductAssignedToStock = $isProductAssignedToStock;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->isSourceItemManagementAllowedForSku = $isSourceItemManagementAllowedForSku;
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku, int $stockId): StockItemConfigurationInterface
    {
        if ($this->defaultStockProvider->getId() !== $stockId
            && true === $this->isSourceItemManagementAllowedForSku->execute($sku)
            && false === $this->isProductAssignedToStock->execute($sku, $stockId)) {
            throw new SkuIsNotAssignedToStockException(
                __('The requested sku is not assigned to given stock.')
            );
        }

        $factory = $this->admitsBackorders($sku)
            ? $this->stockItemConfigurationFactory
            : $this->nonBackorderableStockItemConfigurationFactory;

        return $factory->create(
            [
                'stockItem' => $this->getStockItemConfigurationBySku->execute($sku)
            ]
        );
    }

    /**
     * Check whether the product type of the sku admits backorders.
     *
     * @param string $sku
     * @return bool
     */
    private function admitsBackorders(string $sku): bool
    {
        $nonBackorderableTypes = $this->getNonBackorderableProductTypes->execute();
        if (!$nonBackorderableTypes) {
            return true;
        }
        $type = $this->getProductTypesBySkus->execute([$sku])[$sku] ?? null;

        return !in_array($type, $nonBackorderableTypes, true);
    }
}
