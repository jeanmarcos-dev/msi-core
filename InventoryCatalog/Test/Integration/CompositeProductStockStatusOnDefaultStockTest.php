<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Bundle\Test\Fixture\Link as BundleSelectionFixture;
use Magento\Bundle\Test\Fixture\Option as BundleOptionFixture;
use Magento\Bundle\Test\Fixture\Product as BundleProductFixture;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\InventoryApi\Test\Fixture\SourceItem as SourceItemFixture;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CompositeProductStockStatusOnDefaultStockTest extends TestCase
{
    private const DEFAULT_STOCK_ID = 1;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var GetStockItemData
     */
    private $getStockItemData;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->fixtures = $objectManager->get(DataFixtureStorageManager::class)->getStorage();
        $this->stockRegistry = $objectManager->get(StockRegistryInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->getStockItemData = $objectManager->get(GetStockItemData::class);
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default']),
        DataFixture(BundleSelectionFixture::class, ['sku' => '$child.sku$', 'qty' => 1], 'link'),
        DataFixture(BundleOptionFixture::class, ['product_links' => ['$link$'], 'required' => true], 'option'),
        DataFixture(BundleProductFixture::class, ['_options' => ['$option$']], 'parent'),
    ]
    public function testBundleMarkedOutOfStockLeavesTheDefaultStockIndexAndComesBack(): void
    {
        $this->assertStockStatusFollowsTheStockItemApi();
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default']),
        DataFixture(GroupedProductFixture::class, ['product_links' => ['$child.sku$']], 'parent'),
    ]
    public function testGroupedMarkedOutOfStockLeavesTheDefaultStockIndexAndComesBack(): void
    {
        $this->assertStockStatusFollowsTheStockItemApi();
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default']),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'parent'
        ),
    ]
    public function testConfigurableMarkedOutOfStockLeavesTheDefaultStockIndexAndComesBack(): void
    {
        $this->assertStockStatusFollowsTheStockItemApi();
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default']),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'parent'
        ),
    ]
    public function testConfigurableSavedOutOfStockThroughTheProductRepositoryLeavesTheDefaultStockIndex(): void
    {
        $sku = $this->fixtures->get('parent')->getSku();
        $this->assertSame(1, $this->getIndexedSalability($sku));

        $product = $this->productRepository->get($sku, true, null, true);
        $product->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 0]);
        $this->productRepository->save($product);

        $this->assertSame(0, $this->getIndexedSalability($sku));
    }

    private function assertStockStatusFollowsTheStockItemApi(): void
    {
        $sku = $this->fixtures->get('parent')->getSku();
        $this->assertSame(1, $this->getIndexedSalability($sku));

        $this->setIsInStock($sku, false);
        $this->assertSame(0, $this->getIndexedSalability($sku));

        $this->setIsInStock($sku, true);
        $this->assertSame(1, $this->getIndexedSalability($sku));
    }

    private function setIsInStock(string $sku, bool $isInStock): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $stockItem->setIsInStock($isInStock);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
    }

    private function getIndexedSalability(string $sku): ?int
    {
        $data = $this->getStockItemData->execute($sku, self::DEFAULT_STOCK_ID);

        return $data === null ? null : (int) $data[GetStockItemDataInterface::IS_SALABLE];
    }
}
