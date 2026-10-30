<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Integration\Plugin\Model\Product\Type\Configurable;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Helper\Stock;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\GetStockItemData\CacheStorage;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
 * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
 */
class IsSalablePerWebsiteTest extends TestCase
{
    private const FIRST_STORE_ID = 1;
    private const CHILDREN = ['simple_10', 'simple_20'];

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var int
     */
    private int $secondStoreId;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->connection = $this->objectManager->get(ResourceConnection::class)->getConnection();
        $this->secondStoreId = (int)$this->objectManager->get(StoreManagerInterface::class)
            ->getStore('fixture_second_store')
            ->getId();
        $secondWebsiteId = (int)$this->connection->fetchOne("SELECT website_id FROM store_website WHERE code = 'test'");
        $this->connection->insertOnDuplicate(
            'inventory_stock_sales_channel',
            ['type' => 'website', 'code' => 'test', 'stock_id' => 1],
            ['stock_id']
        );
        $this->assignToWebsites(array_merge(self::CHILDREN, ['configurable']), [1, $secondWebsiteId]);
    }

    public function testConfigurableIsSalableInEveryWebsiteWhereAChildIsEnabledAndAssigned(): void
    {
        $this->reindex();

        self::assertTrue($this->isSalableIn(self::FIRST_STORE_ID));
        self::assertTrue($this->isSalableIn($this->secondStoreId));
    }

    public function testChildrenDisabledInOneWebsiteLeaveTheConfigurableSalableInTheOther(): void
    {
        $this->setStatus(self::CHILDREN, self::FIRST_STORE_ID, Status::STATUS_DISABLED);
        $this->reindex();

        self::assertFalse($this->isSalableIn(self::FIRST_STORE_ID));
        self::assertTrue($this->isSalableIn($this->secondStoreId));
    }

    public function testChildrenDisabledByDefaultAndEnabledInAWebsiteAreSalableOnlyThere(): void
    {
        $this->setStatus(self::CHILDREN, 0, Status::STATUS_DISABLED);
        $this->setStatus(self::CHILDREN, self::FIRST_STORE_ID, Status::STATUS_ENABLED);
        $this->reindex();

        self::assertTrue($this->isSalableIn(self::FIRST_STORE_ID));
        self::assertFalse($this->isSalableIn($this->secondStoreId));
    }

    public function testChildrenNotAssignedToAWebsiteDoNotMakeTheConfigurableSalableThere(): void
    {
        $this->assignToWebsites(self::CHILDREN, [1]);
        $this->reindex();

        self::assertTrue($this->isSalableIn(self::FIRST_STORE_ID));
        self::assertFalse($this->isSalableIn($this->secondStoreId));
    }

    public function testEachWebsiteNeedsOnlyOneChildThatIsEnabledThere(): void
    {
        $this->setStatus(['simple_10'], $this->secondStoreId, Status::STATUS_DISABLED);
        $this->setStatus(['simple_20'], self::FIRST_STORE_ID, Status::STATUS_DISABLED);
        $this->reindex();

        self::assertTrue($this->isSalableIn(self::FIRST_STORE_ID));
        self::assertTrue($this->isSalableIn($this->secondStoreId));
    }

    public function testChildrenDisabledEverywhereMakeTheConfigurableNotSalable(): void
    {
        $this->setStatus(self::CHILDREN, 0, Status::STATUS_DISABLED);
        $this->reindex();

        self::assertFalse($this->isSalableIn(self::FIRST_STORE_ID));
        self::assertFalse($this->isSalableIn($this->secondStoreId));
    }

    public function testListingAnswersMatchTheOnesOfTheProductPage(): void
    {
        $this->setStatus(self::CHILDREN, self::FIRST_STORE_ID, Status::STATUS_DISABLED);
        $this->reindex();

        self::assertFalse($this->listedConfigurableIsSalableIn(self::FIRST_STORE_ID));
        self::assertTrue($this->listedConfigurableIsSalableIn($this->secondStoreId));
    }

    private function isSalableIn(int $storeId): bool
    {
        $this->resetRuntimeState();
        $this->objectManager->get(StoreManagerInterface::class)->setCurrentStore($storeId);

        return (bool)$this->productRepository->get('configurable', false, $storeId, true)->isSalable();
    }

    private function listedConfigurableIsSalableIn(int $storeId): bool
    {
        $this->resetRuntimeState();
        $this->objectManager->get(StoreManagerInterface::class)->setCurrentStore($storeId);
        $collection = $this->objectManager->create(Collection::class);
        $collection->setStoreId($storeId)
            ->addAttributeToFilter('type_id', Configurable::TYPE_CODE)
            ->addStoreFilter($storeId);
        $this->objectManager->get(Stock::class)->addIsInStockFilterToCollection($collection);
        $collection->load();

        self::assertCount(1, $collection);

        return (bool)$collection->getFirstItem()->isSalable();
    }

    private function assignToWebsites(array $skus, array $websiteIds): void
    {
        foreach ($skus as $sku) {
            $product = $this->productRepository->get($sku, true, 0, true);
            $product->setWebsiteIds($websiteIds);
            $this->productRepository->save($product);
        }
    }

    private function setStatus(array $skus, int $storeId, int $status): void
    {
        foreach ($skus as $sku) {
            $product = $this->productRepository->get($sku, true, $storeId, true);
            $product->setStoreId($storeId)->setStatus($status);
            $this->productRepository->save($product);
        }
    }

    private function reindex(): void
    {
        $this->objectManager->get(StockIndexer::class)->executeFull();
    }

    private function resetRuntimeState(): void
    {
        $this->objectManager->get(CacheStorage::class)->_resetState();
        $this->objectManager->get(CacheInterface::class)->clean();
        $this->objectManager->get(Configurable::class)->_resetState();
        $this->productRepository->cleanCache();
    }
}
