<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Integration\Plugin\GraphQl;

use GraphQL\Language\AST\OperationDefinitionNode;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryGraphQl\Model\Resolver\StockStatusProvider;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea graphql
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
 * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
 */
class ConfigurableStockStatusPluginTest extends TestCase
{
    private const FIRST_STORE_ID = 1;
    private const CHILDREN = ['simple_10', 'simple_20'];

    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var int
     */
    private int $secondStoreId;

    /**
     * @var int
     */
    private int $secondWebsiteId;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $this->secondStoreId = (int)$objectManager->get(StoreManagerInterface::class)
            ->getStore('fixture_second_store')
            ->getId();
        $this->secondWebsiteId = (int)$this->connection->fetchOne(
            "SELECT website_id FROM store_website WHERE code = 'test'"
        );
        $this->connection->insertOnDuplicate(
            'inventory_stock_sales_channel',
            ['type' => 'website', 'code' => 'test', 'stock_id' => 1],
            ['stock_id']
        );
        foreach (array_merge(self::CHILDREN, ['configurable']) as $sku) {
            $this->connection->insertOnDuplicate(
                'catalog_product_website',
                ['product_id' => $this->productId($sku), 'website_id' => $this->secondWebsiteId],
                ['website_id']
            );
        }
    }

    public function testConfigurableIsInStockInEveryWebsiteWhereAChildIsSalable(): void
    {
        $this->reindex();

        self::assertSame('IN_STOCK', $this->stockStatusIn(self::FIRST_STORE_ID));
        self::assertSame('IN_STOCK', $this->stockStatusIn($this->secondStoreId));
    }

    public function testConfigurableIsOutOfStockInTheWebsiteWhereItsChildrenAreDisabled(): void
    {
        $this->setStatus(self::FIRST_STORE_ID, Status::STATUS_DISABLED);
        $this->reindex();

        self::assertSame('OUT_OF_STOCK', $this->stockStatusIn(self::FIRST_STORE_ID));
        self::assertSame('IN_STOCK', $this->stockStatusIn($this->secondStoreId));
    }

    public function testConfigurableIsOutOfStockInTheWebsiteWhereItsChildrenAreNotAssigned(): void
    {
        foreach (self::CHILDREN as $sku) {
            $this->connection->delete(
                'catalog_product_website',
                ['product_id = ?' => $this->productId($sku), 'website_id = ?' => $this->secondWebsiteId]
            );
        }
        $this->reindex();

        self::assertSame('IN_STOCK', $this->stockStatusIn(self::FIRST_STORE_ID));
        self::assertSame('OUT_OF_STOCK', $this->stockStatusIn($this->secondStoreId));
    }

    private function stockStatusIn(int $storeId): string
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(StoreManagerInterface::class)->setCurrentStore($storeId);
        $product = $objectManager->create(Product::class);
        $product->setStoreId($storeId)->load($this->productId('configurable'));
        $info = $this->createMock(ResolveInfo::class);
        $info->operation = new OperationDefinitionNode(['operation' => 'query']);

        return $objectManager->get(StockStatusProvider::class)->resolve(
            $this->createMock(Field::class),
            null,
            $info,
            ['model' => $product]
        );
    }

    private function productId(string $sku): int
    {
        return (int)$this->connection->fetchOne('SELECT entity_id FROM catalog_product_entity WHERE sku = ?', [$sku]);
    }

    private function setStatus(int $storeId, int $status): void
    {
        $statusId = (int)Bootstrap::getObjectManager()->get(Config::class)
            ->getAttribute('catalog_product', 'status')
            ->getId();
        foreach (self::CHILDREN as $sku) {
            $this->connection->insertOnDuplicate('catalog_product_entity_int', [
                'attribute_id' => $statusId,
                'store_id' => $storeId,
                'entity_id' => $this->productId($sku),
                'value' => $status,
            ], ['value']);
        }
    }

    private function reindex(): void
    {
        Bootstrap::getObjectManager()->get(StockIndexer::class)->executeFull();
    }
}
