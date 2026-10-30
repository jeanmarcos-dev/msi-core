<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Integration\Plugin\Model\Product\Type\Configurable;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class IsSalablePluginTest extends TestCase
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        $this->productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $this->productRepository->cleanCache();
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
     */
    public function testConfigurableIsNotSalableOnceItsChildrenWentOutOfStock(): void
    {
        $this->markOutOfStock(['simple_10', 'simple_20']);

        $configurable = $this->productRepository->get('configurable', false, null, true);

        self::assertFalse($configurable->getIsSalable());
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
     */
    public function testConfigurableStaysSalableWhileOneChildIsInStock(): void
    {
        $this->markOutOfStock(['simple_10']);

        $configurable = $this->productRepository->get('configurable', false, null, true);

        self::assertTrue($configurable->getIsSalable());
    }

    private function markOutOfStock(array $skus): void
    {
        foreach ($skus as $sku) {
            $product = $this->productRepository->get($sku);
            $product->addData([
                'stock_data' => [
                    'use_config_manage_stock' => 1,
                    'is_in_stock' => StockStatusInterface::STATUS_OUT_OF_STOCK,
                ],
            ]);
            $this->productRepository->save($product);
        }
    }
}
