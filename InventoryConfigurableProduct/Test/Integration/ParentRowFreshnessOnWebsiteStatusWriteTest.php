<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ParentRowFreshnessOnWebsiteStatusWriteTest extends TestCase
{
    private const DEFAULT_STOCK_ID = 1;

    private const STORE_VIEW_ID = 1;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var GetStockItemData
     */
    private GetStockItemData $getStockItemData;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->getStockItemData = $objectManager->get(GetStockItemData::class);
    }

    protected function tearDown(): void
    {
        $this->storeManager->setCurrentStore(self::STORE_VIEW_ID);
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'parent'
        ),
        DataFixture(IndexerFixture::class),
    ]
    public function testDisablingAndReEnablingAChildAtWebsiteScopeKeepsTheParentRowFresh(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $childSku = $fixtures->get('child')->getSku();
        $parentSku = $fixtures->get('parent')->getSku();
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        self::assertSame(1, $this->getParentIsSalable($parentSku));

        $this->saveStatus($childSku, self::STORE_VIEW_ID, Status::STATUS_DISABLED);
        self::assertSame(
            0,
            $this->getParentIsSalable($parentSku),
            'a child disabled at website scope must take the parent row to 0'
        );

        $this->saveStatus($childSku, self::STORE_VIEW_ID, Status::STATUS_ENABLED);
        self::assertSame(
            1,
            $this->getParentIsSalable($parentSku),
            'a child re-enabled at website scope must bring the parent row back to 1'
        );
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'parent'
        ),
        DataFixture(IndexerFixture::class),
    ]
    public function testEnablingAtWebsiteScopeAChildDisabledByDefaultMakesTheParentRowSalable(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $childSku = $fixtures->get('child')->getSku();
        $parentSku = $fixtures->get('parent')->getSku();
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        $this->saveStatus($childSku, Store::DEFAULT_STORE_ID, Status::STATUS_DISABLED);
        self::assertSame(
            0,
            $this->getParentIsSalable($parentSku),
            'a child disabled by default and in no website must take the parent row to 0'
        );

        $this->saveStatus($childSku, self::STORE_VIEW_ID, Status::STATUS_ENABLED);
        self::assertSame(
            1,
            $this->getParentIsSalable($parentSku),
            'a child enabled at website scope over a disabled default must make the parent row salable'
        );
    }

    private function saveStatus(string $sku, int $storeId, int $status): void
    {
        $product = $this->productRepository->get($sku, true, $storeId, true);
        $product->setStatus($status);
        $this->productRepository->save($product);
    }

    private function getParentIsSalable(string $parentSku): int
    {
        $row = $this->getStockItemData->execute($parentSku, self::DEFAULT_STOCK_ID);

        return (int) $row[GetStockItemDataInterface::IS_SALABLE];
    }
}
