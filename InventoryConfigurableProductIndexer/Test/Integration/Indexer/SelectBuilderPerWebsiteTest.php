<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProductIndexer\Test\Integration\Indexer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryConfigurableProductIndexer\Indexer\SelectBuilder;
use Magento\InventoryIndexer\Indexer\Stock\DataProvider;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(SelectBuilder::class),
    DbIsolation(false),
    DataFixture('Magento_InventoryApi::Test/_files/sources.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stocks.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stock_source_links.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/websites_with_stores.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php'),
    DataFixture('Magento/ConfigurableProduct/_files/configurable_attribute.php'),
    DataFixture('Magento_InventoryConfigurableProductIndexer::Test/_files/product_configurable_multiple.php'),
    DataFixture('Magento_InventoryConfigurableProductIndexer::Test/_files/source_items_configurable_multiple.php'),
]
class SelectBuilderPerWebsiteTest extends TestCase
{
    private const SKU = 'configurable_1';
    private const CHILDREN = ['simple_11', 'simple_21'];
    private const EU_STOCK = 10;
    private const US_STOCK = 20;
    private const GLOBAL_STOCK = 30;

    /**
     * @var DataProvider
     */
    private $dataProvider;

    /**
     * @var AdapterInterface
     */
    private $connection;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->dataProvider = $objectManager->create(DataProvider::class);
        $this->connection = $objectManager->get(ResourceConnection::class)->getConnection();
    }

    public function testChildrenDisabledInTheWebsiteOfTheStockAreNotCounted(): void
    {
        $this->setChildrenStatus($this->defaultStoreOf('eu_website'), Status::STATUS_DISABLED);

        self::assertSame(0, $this->isSalable(self::EU_STOCK));
        self::assertSame(1, $this->isSalable(self::US_STOCK));
        self::assertSame(1, $this->isSalable(self::GLOBAL_STOCK));
    }

    public function testChildrenEnabledOnlyInTheWebsiteOfTheStockAreCounted(): void
    {
        $this->setChildrenStatus(0, Status::STATUS_DISABLED);
        $this->setChildrenStatus($this->defaultStoreOf('eu_website'), Status::STATUS_ENABLED);

        self::assertSame(1, $this->isSalable(self::EU_STOCK));
        self::assertSame(0, $this->isSalable(self::US_STOCK));
        self::assertSame(0, $this->isSalable(self::GLOBAL_STOCK));
    }

    public function testChildEnabledInAnyWebsiteOfTheStockIsCounted(): void
    {
        $this->assignWebsiteToStock('global_website', self::EU_STOCK);
        $this->setChildrenStatus($this->defaultStoreOf('eu_website'), Status::STATUS_DISABLED);
        $this->setChildrenStatus($this->defaultStoreOf('global_website'), Status::STATUS_ENABLED);

        self::assertSame(1, $this->isSalable(self::EU_STOCK));
    }

    public function testChildrenDisabledInEveryWebsiteOfTheStockAreNotCounted(): void
    {
        $this->assignWebsiteToStock('global_website', self::EU_STOCK);
        $this->setChildrenStatus($this->defaultStoreOf('eu_website'), Status::STATUS_DISABLED);
        $this->setChildrenStatus($this->defaultStoreOf('global_website'), Status::STATUS_DISABLED);

        self::assertSame(0, $this->isSalable(self::EU_STOCK));
    }

    public function testQuantityIsNotMultipliedByTheWebsitesOfTheStock(): void
    {
        $single = $this->row(self::EU_STOCK);
        $this->assignWebsiteToStock('global_website', self::EU_STOCK);
        $double = $this->row(self::EU_STOCK);

        self::assertEquals($single['quantity'], $double['quantity']);
        self::assertEquals(300, $double['quantity']);
    }

    public function testProductAssignmentToTheWebsiteIsNotPartOfTheRow(): void
    {
        $assignments = (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM catalog_product_website pw'
            . ' JOIN catalog_product_entity e ON e.entity_id = pw.product_id'
            . ' JOIN store_website sw ON sw.website_id = pw.website_id'
            . ' WHERE e.sku = ? AND sw.code = ?',
            [self::CHILDREN[0], 'eu_website']
        );

        self::assertSame(0, $assignments);
        self::assertSame(1, $this->isSalable(self::EU_STOCK));
    }

    public function testStockWithoutWebsitesFollowsTheStatusOfTheDefaultScope(): void
    {
        $this->connection->delete('inventory_stock_sales_channel', ['stock_id = ?' => self::GLOBAL_STOCK]);
        $this->setChildrenStatus($this->defaultStoreOf('global_website'), Status::STATUS_DISABLED);

        self::assertSame(1, $this->isSalable(self::GLOBAL_STOCK));

        $this->setChildrenStatus(0, Status::STATUS_DISABLED);

        self::assertSame(0, $this->isSalable(self::GLOBAL_STOCK));
    }

    private function isSalable(int $stockId): int
    {
        return (int)$this->row($stockId)['is_salable'];
    }

    private function row(int $stockId): array
    {
        $rows = $this->dataProvider->getSiblingsData($stockId, ConfigurableType::TYPE_CODE, [self::SKU]);
        self::assertCount(1, $rows);

        return reset($rows);
    }

    private function setChildrenStatus(int $storeId, int $status): void
    {
        $linkField = Bootstrap::getObjectManager()->get(MetadataPool::class)
            ->getMetadata(ProductInterface::class)
            ->getLinkField();
        foreach (self::CHILDREN as $sku) {
            $this->connection->insertOnDuplicate('catalog_product_entity_int', [
                'attribute_id' => $this->statusAttributeId(),
                'store_id' => $storeId,
                $linkField => $this->connection->fetchOne(
                    "SELECT $linkField FROM catalog_product_entity WHERE sku = ?",
                    [$sku]
                ),
                'value' => $status,
            ], ['value']);
        }
    }

    private function assignWebsiteToStock(string $websiteCode, int $stockId): void
    {
        $this->connection->update(
            'inventory_stock_sales_channel',
            ['stock_id' => $stockId],
            ['type = ?' => 'website', 'code = ?' => $websiteCode]
        );
    }

    private function defaultStoreOf(string $websiteCode): int
    {
        $storeId = (int)$this->connection->fetchOne(
            'SELECT sg.default_store_id FROM store_website sw'
            . ' JOIN store_group sg ON sg.group_id = sw.default_group_id WHERE sw.code = ?',
            [$websiteCode]
        );
        self::assertGreaterThan(0, $storeId);

        return $storeId;
    }

    private function statusAttributeId(): int
    {
        return (int)Bootstrap::getObjectManager()->get(Config::class)
            ->getAttribute('catalog_product', ProductInterface::STATUS)
            ->getId();
    }
}
