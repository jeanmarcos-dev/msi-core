<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySetupFixtureGenerator\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\Setup\Model\FixtureGenerator\ProductGenerator;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 */
class ProductGenerationTest extends TestCase
{
    private const SKU_PATTERN = 'toolkit_%s_%d';
    private const PRODUCT_COUNT = 3;
    private const TEMPLATE_QTY = 100500;

    /**
     * @var ProductGenerator
     */
    private $productGenerator;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var GetProductSalableQtyInterface
     */
    private $getProductSalableQty;

    /**
     * @var string
     */
    private $skuToken;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->productGenerator = $objectManager->get(ProductGenerator::class);
        $this->resourceConnection = $objectManager->get(ResourceConnection::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->getProductSalableQty = $objectManager->get(GetProductSalableQtyInterface::class);
        $this->skuToken = substr(hash('sha256', $this->name()), 0, 8);
    }

    protected function tearDown(): void
    {
        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        foreach ($this->generatedSkus() as $sku) {
            $this->productRepository->deleteById($sku);
        }
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', false);
    }

    public function testGeneratesSourceItemsForEveryProduct(): void
    {
        $this->generate();

        foreach ($this->generatedSkus() as $sku) {
            $row = $this->fetchRow('inventory_source_item', $sku);
            self::assertSame('default', $row['source_code']);
            self::assertEquals(self::TEMPLATE_QTY, $row['quantity']);
            self::assertSame('1', (string)$row['status']);
        }
    }

    public function testGeneratesStockItemConfigurationForEveryProduct(): void
    {
        $this->generate();

        foreach ($this->generatedSkus() as $sku) {
            $row = $this->fetchRow('inventory_stock_item_configuration', $sku);
            self::assertSame('1', (string)$row['is_in_stock']);
            self::assertSame('1', (string)$row['use_config_manage_stock']);
        }
    }

    public function testGeneratesDefaultStockIndexRowsForEveryProduct(): void
    {
        $this->generate();

        foreach ($this->generatedSkus() as $sku) {
            $row = $this->fetchRow('inventory_stock_1', $sku);
            self::assertEquals(self::TEMPLATE_QTY, $row['quantity']);
            self::assertSame('1', (string)$row['is_salable']);
            self::assertEquals(self::TEMPLATE_QTY, $this->getProductSalableQty->execute($sku, 1));
        }
    }

    public function testGeneratesAnAdjustmentForEveryProduct(): void
    {
        $this->generate();

        foreach ($this->generatedSkus() as $sku) {
            $row = $this->fetchRow('inventory_source_item_adjustment', $sku);
            self::assertSame('default', $row['source_code']);
            self::assertEquals(self::TEMPLATE_QTY, $row['quantity_after']);
        }
    }

    public function testWritesNoRowWithoutSku(): void
    {
        $this->generate();

        $tables = [
            'inventory_source_item',
            'inventory_stock_item_configuration',
            'inventory_stock_1',
            'inventory_source_item_adjustment',
        ];
        foreach ($tables as $table) {
            self::assertSame(0, $this->countRowsWithSku($table, ''), $table);
        }
    }

    public function testLeavesTheWebsiteDateTableAsItWas(): void
    {
        $before = $this->fetchWebsiteDates();

        $this->generate();

        self::assertSame($before, $this->fetchWebsiteDates());
    }

    private function generate(): void
    {
        $this->productGenerator->generate(self::PRODUCT_COUNT, [
            'name' => static function ($productId, $entityNumber) {
                return 'Toolkit Product ' . $entityNumber;
            },
            'sku' => function ($productId, $entityNumber) {
                return sprintf(self::SKU_PATTERN, $this->skuToken, $entityNumber);
            },
            'price' => static function () {
                return 10;
            },
            'url_key' => function ($productId, $entityNumber) {
                return 'toolkit-' . $this->skuToken . '-' . $entityNumber;
            },
            'category_ids' => static function () {
                return 2;
            },
        ]);
    }

    private function generatedSkus(): array
    {
        $skus = [];
        for ($number = 0; $number < self::PRODUCT_COUNT; $number++) {
            $skus[] = sprintf(self::SKU_PATTERN, $this->skuToken, $number);
        }

        return $skus;
    }

    private function fetchRow(string $table, string $sku): array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName($table))
                ->where('sku = ?', $sku)
        );
        self::assertIsArray($row, sprintf('"%s" has no row for "%s"', $table, $sku));

        return $row;
    }

    private function countRowsWithSku(string $table, string $sku): int
    {
        $connection = $this->resourceConnection->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName($table), ['count' => 'COUNT(*)'])
                ->where('sku = ?', $sku)
        );
    }

    private function fetchWebsiteDates(): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchAll(
            $connection->select()->from($this->resourceConnection->getTableName('catalog_product_index_website'))
        );
    }
}
