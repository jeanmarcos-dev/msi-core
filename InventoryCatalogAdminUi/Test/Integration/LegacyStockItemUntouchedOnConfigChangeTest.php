<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * CatalogInventory answers a change of the global stock configuration by rewriting
 * cataloginventory_stock_item. MSI stopped reading that table, so the rewrite reaches nothing while
 * still costing a full pass over the catalog, and it would drown the write detector in noise the
 * store owner cannot act on. Its observer is disabled, and this pins that down.
 *
 * @magentoAppArea adminhtml
 */
class LegacyStockItemUntouchedOnConfigChangeTest extends TestCase
{
    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        $this->eventManager = Bootstrap::getObjectManager()->get(EventManagerInterface::class);
        $this->resourceConnection = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $this->productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoConfigFixture default_store cataloginventory/item_options/manage_stock 1
     * @magentoConfigFixture default_store cataloginventory/item_options/backorders 0
     * @magentoConfigFixture default_store cataloginventory/item_options/min_qty 0
     */
    public function testConfigChangeLeavesTheFrozenStockItemRowUntouched(): void
    {
        $productId = (int)$this->productRepository->get('SKU-1')->getId();
        $this->giveTheProductALegacyRowTheLegacyObserverWouldRewrite($productId);

        $this->eventManager->dispatch(
            'admin_system_config_changed_section_cataloginventory',
            ['website' => 0, 'changed_paths' => ['cataloginventory/item_options/manage_stock']]
        );

        $row = $this->getLegacyRow($productId);
        self::assertSame('1', (string)$row['is_in_stock']);
        self::assertSame('0', (string)$row['stock_status_changed_auto']);
    }

    /**
     * Write the exact row shape updateSetOutOfStock() looks for: in stock, following the global
     * configuration everywhere, and holding no quantity.
     *
     * @param int $productId
     * @return void
     */
    private function giveTheProductALegacyRowTheLegacyObserverWouldRewrite(int $productId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        $connection->delete($table, ['product_id = ?' => $productId]);
        $connection->insert(
            $table,
            [
                'product_id' => $productId,
                'stock_id' => 1,
                'website_id' => 0,
                'qty' => 0,
                'is_in_stock' => 1,
                'use_config_manage_stock' => 1,
                'use_config_backorders' => 1,
                'use_config_min_qty' => 1,
                'stock_status_changed_auto' => 0,
            ]
        );
    }

    /**
     * Read back the legacy row of a product.
     *
     * @param int $productId
     * @return array
     */
    private function getLegacyRow(int $productId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('cataloginventory_stock_item'))
            ->where('product_id = ?', $productId);

        return (array)$connection->fetchRow($select);
    }
}
