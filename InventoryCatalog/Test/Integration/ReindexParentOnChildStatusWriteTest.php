<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\ResourceConnection;
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

class ReindexParentOnChildStatusWriteTest extends TestCase
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
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var GetStockItemData
     */
    private GetStockItemData $getStockItemData;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->resourceConnection = $objectManager->get(ResourceConnection::class);
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
    public function testReEnablingAChildAtWebsiteScopeWithTheAdminStoreActiveReindexesItsParent(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $childSku = $fixtures->get('child')->getSku();
        $parentSku = $fixtures->get('parent')->getSku();
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        $this->saveStatusAtStoreView($childSku, Status::STATUS_DISABLED);
        $this->markParentRowStale($parentSku);
        $this->saveStatusAtStoreView($childSku, Status::STATUS_ENABLED);

        self::assertSame(
            1,
            $this->getParentIsSalable($parentSku),
            'the parent row must be recomputed when a child is re-enabled at website scope'
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
    public function testSavingAChildWithoutChangingItsStatusLeavesTheParentRowAlone(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $childSku = $fixtures->get('child')->getSku();
        $parentSku = $fixtures->get('parent')->getSku();
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        $this->saveStatusAtStoreView($childSku, Status::STATUS_ENABLED);
        $this->markParentRowStale($parentSku);
        $this->saveStatusAtStoreView($childSku, Status::STATUS_ENABLED);

        self::assertSame(
            0,
            $this->getParentIsSalable($parentSku),
            'a save that leaves the status as it was must not reindex the parent'
        );
    }

    private function saveStatusAtStoreView(string $sku, int $status): void
    {
        $product = $this->productRepository->get($sku, true, self::STORE_VIEW_ID, true);
        $product->setStatus($status);
        $this->productRepository->save($product);
    }

    private function markParentRowStale(string $parentSku): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName('inventory_stock_' . self::DEFAULT_STOCK_ID),
            [GetStockItemDataInterface::IS_SALABLE => 0],
            ['sku = ?' => $parentSku]
        );
    }

    private function getParentIsSalable(string $parentSku): int
    {
        $row = $this->getStockItemData->execute($parentSku, self::DEFAULT_STOCK_ID);

        return (int) $row[GetStockItemDataInterface::IS_SALABLE];
    }
}
