<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;

class AdjustmentHistory extends AbstractModifier
{
    private const LISTING = 'inventory_adjustment_product_listing';

    /**
     * @param LocatorInterface $locator
     * @param AuthorizationInterface $authorization
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data)
    {
        return $data;
    }

    /**
     * Add a closed stock history section to the form of a saved product
     *
     * @param array $meta
     * @return array
     */
    public function modifyMeta(array $meta)
    {
        $product = $this->locator->getProduct();
        if (!$product->getId() || !$this->authorization->isAllowed('Magento_InventoryAdjustment::view')) {
            return $meta;
        }
        $meta['adjustment_history'] = [
            'arguments' => ['data' => ['config' => [
                'componentType' => 'fieldset',
                'component' => 'Magento_InventoryAdjustmentAdminUi/js/product/form/adjustment-history',
                'label' => __('Stock History'),
                'collapsible' => true,
                'opened' => false,
                'dataScope' => '',
                'sortOrder' => 6,
            ]]],
            'children' => [
                'adjustment_history_listing' => [
                    'arguments' => ['data' => ['config' => [
                        'componentType' => 'container',
                        'component' => 'Magento_Ui/js/form/components/insert-listing',
                        'autoRender' => false,
                        'ns' => self::LISTING,
                        'externalProvider' => self::LISTING . '.' . self::LISTING . '_data_source',
                        'render_url' => $this->urlBuilder->getUrl('mui/index/render'),
                        'params' => ['sku' => (string)$product->getSku()],
                        'dataLinks' => ['imports' => false, 'exports' => false],
                        'realTimeLink' => false,
                        'behaviourType' => 'simple',
                        'externalFilterMode' => false,
                    ]]],
                ],
            ],
        ];

        return $meta;
    }
}
