<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Integration\CatalogSearch;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryCatalogSearchConfigurableProduct\Model\CatalogSearch\Indexer\ConfigurableChildStockStatusModifier;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
 * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
 */
class ConfigurableChildStockStatusModifierTest extends TestCase
{
    private const FIRST_STORE_ID = 1;
    private const CHILDREN = ['simple_10', 'simple_20'];

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
        $objectManager = Bootstrap::getObjectManager();
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $this->secondStoreId = (int)$objectManager->get(StoreManagerInterface::class)
            ->getStore('fixture_second_store')
            ->getId();
        $secondWebsiteId = (int)$this->connection->fetchOne("SELECT website_id FROM store_website WHERE code = 'test'");
        $this->connection->insertOnDuplicate(
            'inventory_stock_sales_channel',
            ['type' => 'website', 'code' => 'test', 'stock_id' => 1],
            ['stock_id']
        );
        $this->assignToWebsites(array_merge(self::CHILDREN, ['configurable']), [1, $secondWebsiteId]);
        $objectManager->get(StockIndexer::class)->executeFull();
    }

    public function testConfigurableIsIndexedInTheWebsitesWhereAChildIsEnabledAssignedAndSalable(): void
    {
        self::assertTrue($this->isIndexedIn(self::FIRST_STORE_ID));
        self::assertTrue($this->isIndexedIn($this->secondStoreId));
    }

    public function testConfigurableIsNotIndexedInTheWebsiteWhereItsChildrenAreNotAssigned(): void
    {
        $this->assignToWebsites(self::CHILDREN, [1]);

        self::assertTrue($this->isIndexedIn(self::FIRST_STORE_ID));
        self::assertFalse($this->isIndexedIn($this->secondStoreId));
    }

    public function testConfigurableIsNotIndexedInTheWebsiteWhereItsChildrenAreDisabled(): void
    {
        foreach (self::CHILDREN as $sku) {
            $product = $this->productRepository->get($sku, true, self::FIRST_STORE_ID, true);
            $product->setStoreId(self::FIRST_STORE_ID)->setStatus(Status::STATUS_DISABLED);
            $this->productRepository->save($product);
        }

        self::assertFalse($this->isIndexedIn(self::FIRST_STORE_ID));
        self::assertTrue($this->isIndexedIn($this->secondStoreId));
    }

    private function isIndexedIn(int $storeId): bool
    {
        $select = $this->connection->select()
            ->from(['e' => $this->connection->getTableName('catalog_product_entity')], 'sku')
            ->where('e.sku = ?', 'configurable');
        Bootstrap::getObjectManager()->get(ConfigurableChildStockStatusModifier::class)->modify($select, $storeId);

        return $this->connection->fetchCol($select) === ['configurable'];
    }

    private function assignToWebsites(array $skus, array $websiteIds): void
    {
        foreach ($skus as $sku) {
            $product = $this->productRepository->get($sku, true, 0, true);
            $product->setWebsiteIds($websiteIds);
            $this->productRepository->save($product);
        }
    }
}
