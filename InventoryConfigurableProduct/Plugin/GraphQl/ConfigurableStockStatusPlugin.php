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
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Report a configurable product as out of stock when no child is salable in the website of the request.
 */
class ConfigurableStockStatusPlugin
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
     * Downgrade the stock status the stock index row gives when the children of the website cannot be sold.
     *
     * @param ResolverInterface $subject
     * @param mixed $result
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolve(
        ResolverInterface $subject,
        $result,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $product = $value['model'] ?? null;
        if ($result !== self::IN_STOCK
            || !$product instanceof ProductInterface
            || $product->getTypeId() !== Configurable::TYPE_CODE
        ) {
            return $result;
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        return $this->salableChildren->exist($product, $storeId) ? self::IN_STOCK : self::OUT_OF_STOCK;
    }
}
