<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Plugin\GraphQl;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use Magento\Store\Model\StoreManagerInterface;

class ConfigurableBatchStockStatusPlugin
{
    private const IN_STOCK = 'IN_STOCK';
    private const OUT_OF_STOCK = 'OUT_OF_STOCK';

    /**
     * @param StoreManagerInterface $storeManager
     * @param SalableChildren $salableChildren
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly SalableChildren $salableChildren
    ) {
    }

    /**
     * Report as out of stock the configurable products without a salable child in the website of the request.
     *
     * @param BatchResolverInterface $subject
     * @param BatchResponse $result
     * @param ContextInterface $context
     * @param Field $field
     * @param array $requests
     * @return BatchResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolve(
        BatchResolverInterface $subject,
        BatchResponse $result,
        ContextInterface $context,
        Field $field,
        array $requests
    ): BatchResponse {
        $inStock = [];
        foreach ($requests as $key => $request) {
            $product = $request->getValue()['model'] ?? null;
            if ($product instanceof ProductInterface
                && $product->getTypeId() === Configurable::TYPE_CODE
                && $result->findResponseFor($request) === self::IN_STOCK
            ) {
                $inStock[$key] = $product;
            }
        }
        if (!$inStock) {
            return $result;
        }

        $storeId = (int)$this->storeManager->getStore()->getId();
        $this->salableChildren->preload(array_values($inStock), $storeId);
        foreach ($inStock as $key => $product) {
            if (!$this->salableChildren->exist($product, $storeId)) {
                $result->addResponse($requests[$key], self::OUT_OF_STOCK);
            }
        }

        return $result;
    }
}
