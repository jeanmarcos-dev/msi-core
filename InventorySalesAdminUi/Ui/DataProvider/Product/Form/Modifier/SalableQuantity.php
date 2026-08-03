<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\InventorySalesAdminUi\Model\AddSourceSalableQuantityBreakdown;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;

/**
 * Product form modifier. Modify form stocks declaration
 */
class SalableQuantity extends AbstractModifier
{
    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    private $isSourceItemManagementAllowedForProductType;

    /**
     * @var LocatorInterface
     */
    private $locator;

    /**
     * @var GetSalableQuantityDataBySku
     */
    private $getSalableQuantityDataBySku;

    /**
     * @var AddSourceSalableQuantityBreakdown
     */
    private $addSourceSalableQuantityBreakdown;

    /**
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     * @param LocatorInterface $locator
     * @param GetSalableQuantityDataBySku $getSalableQuantityDataBySku
     * @param AddSourceSalableQuantityBreakdown $addSourceSalableQuantityBreakdown
     */
    public function __construct(
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        LocatorInterface $locator,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        AddSourceSalableQuantityBreakdown $addSourceSalableQuantityBreakdown
    ) {
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->locator = $locator;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->addSourceSalableQuantityBreakdown = $addSourceSalableQuantityBreakdown;
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data)
    {
        $product = $this->locator->getProduct();

        if ($this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId()) === false
            || null === $product->getId()
        ) {
            return $data;
        }

        $sku = (string) $product->getSku();
        $stockEntries = $this->getSalableQuantityDataBySku->execute($sku);
        $data[$product->getId()]['salable_quantity'] =
            $this->addSourceSalableQuantityBreakdown->execute([$sku => $stockEntries])[$sku];

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta)
    {
        $product = $this->locator->getProduct();

        if ($this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId()) === false
            || null === $product->getId()
        ) {
            return $meta;
        }

        $meta['salable_quantity'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'visible' => 1,
                    ],
                ],
            ],
        ];
        return $meta;
    }
}
