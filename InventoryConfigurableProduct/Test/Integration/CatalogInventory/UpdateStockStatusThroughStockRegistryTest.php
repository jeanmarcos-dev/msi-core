<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Integration\CatalogInventory;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Test\Fixture\SourceItem as SourceItemFixture;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class UpdateStockStatusThroughStockRegistryTest extends TestCase
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
     * @var AreProductsSalableInterface
     */
    private $areProductsSalable;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->fixtures = $objectManager->get(DataFixtureStorageManager::class)->getStorage();
        $this->stockRegistry = $objectManager->get(StockRegistryInterface::class);
        $this->areProductsSalable = $objectManager->get(AreProductsSalableInterface::class);
        $this->resourceConnection = $objectManager->get(ResourceConnection::class);
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'configurable'
        ),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default'])
    ]
    public function testConfigurableMarkedOutOfStockIsNotSalableInDefaultStock(): void
    {
        $sku = $this->fixtures->get('configurable')->getSku();
        $this->assertTrue($this->isSalableInDefaultStock($sku));

        $this->setIsInStock($sku, false);

        $this->assertFalse($this->isSalableInDefaultStock($sku));
        $this->assertSame(0, $this->getIndexedSalability($sku));
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'configurable'
        ),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default'])
    ]
    public function testConfigurableMarkedBackInStockIsSalableInDefaultStockAgain(): void
    {
        $sku = $this->fixtures->get('configurable')->getSku();
        $this->setIsInStock($sku, false);

        $this->setIsInStock($sku, true);

        $this->assertTrue($this->isSalableInDefaultStock($sku));
        $this->assertSame(1, $this->getIndexedSalability($sku));
    }

    #[
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'configurable'
        ),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default'])
    ]
    public function testConfigurableReturnsToPriceIndexWhenChildReturnsToStock(): void
    {
        $childSku = $this->fixtures->get('child')->getSku();
        $configurableSku = $this->fixtures->get('configurable')->getSku();
        $this->assertTrue($this->isInPriceIndex($configurableSku));

        $this->setIsInStock($childSku, false);
        $this->assertFalse($this->isInPriceIndex($configurableSku));

        $this->setIsInStock($childSku, true);

        $this->assertTrue($this->isSalableInDefaultStock($configurableSku));
        $this->assertTrue($this->isInPriceIndex($configurableSku));
    }

    private function setIsInStock(string $sku, bool $isInStock): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $stockItem->setIsInStock($isInStock);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
    }

    private function isSalableInDefaultStock(string $sku): bool
    {
        return current($this->areProductsSalable->execute([$sku], self::DEFAULT_STOCK_ID))->isSalable();
    }

    private function getIndexedSalability(string $sku): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('inventory_stock_' . self::DEFAULT_STOCK_ID);
        $value = $connection->fetchOne($connection->select()->from($table, 'is_salable')->where('sku = ?', $sku));

        return $value === false ? null : (int) $value;
    }

    private function isInPriceIndex(string $sku): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['price' => $this->resourceConnection->getTableName('catalog_product_index_price')], 'entity_id')
            ->join(
                ['product' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'product.entity_id = price.entity_id',
                []
            )
            ->where('product.sku = ?', $sku);

        return $connection->fetchOne($select) !== false;
    }
}
