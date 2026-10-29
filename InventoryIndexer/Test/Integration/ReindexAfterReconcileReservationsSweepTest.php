<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Test\Integration\Indexer\RemoveIndexData;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ReindexAfterReconcileReservationsSweepTest extends TestCase
{
    private const INCREMENT_ID = '000000901';
    private const STOCK_ID = 10;
    private const SKU = 'SKU-1';

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var RemoveIndexData
     */
    private $removeIndexData;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->resourceConnection = $objectManager->get(ResourceConnection::class);
        $this->removeIndexData = $objectManager->get(RemoveIndexData::class);
        $this->removeIndexData->execute([10, 20, 30]);
    }

    protected function tearDown(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName('sales_order'),
            ['increment_id = ?' => self::INCREMENT_ID]
        );
        $connection->delete($this->resourceConnection->getTableName('inventory_reservation'));
        $this->removeIndexData->execute([10, 20, 30]);
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoConfigFixture default_store cataloginventory/source_reservations/enabled 1
     * @magentoDbIsolation disabled
     */
    public function testTheStockIndexMakesTheProductSalableAgainAfterTheCompensationOfAReconcileSweep(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);
        $this->insertTerminalOrder();
        $this->reserve(-8.5);
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);
        self::assertSame(0, $this->indexedIsSalable());

        $result = $objectManager->get(ReconcileReservationsSweep::class)->execute(500);

        self::assertSame([self::STOCK_ID], $result['stock_ids']);
        self::assertSame(1, $this->indexedIsSalable());
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoConfigFixture default_store cataloginventory/source_reservations/enabled 1
     * @magentoDbIsolation disabled
     */
    public function testADryRunLeavesTheStockIndexUntouched(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);
        $this->insertTerminalOrder();
        $this->reserve(-8.5);
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);

        $objectManager->get(ReconcileReservationsSweep::class)->execute(500, true);

        self::assertSame(0, $this->indexedIsSalable());
    }

    private function indexedIsSalable(): int
    {
        $data = Bootstrap::getObjectManager()->get(GetStockItemDataInterface::class)
            ->execute(self::SKU, self::STOCK_ID);

        return (int)$data[GetStockItemDataInterface::IS_SALABLE];
    }

    private function insertTerminalOrder(): void
    {
        $this->resourceConnection->getConnection()->insert(
            $this->resourceConnection->getTableName('sales_order'),
            ['state' => Order::STATE_COMPLETE, 'status' => 'complete', 'increment_id' => self::INCREMENT_ID]
        );
    }

    private function reserve(float $quantity): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $reservation = $objectManager->get(ReservationBuilderInterface::class)
            ->setSku(self::SKU)
            ->setQuantity($quantity)
            ->setStockId(self::STOCK_ID)
            ->setObjectIncrementId(self::INCREMENT_ID)
            ->setMetadata(json_encode([
                'event_type' => 'order_placed',
                'object_type' => 'order',
                'object_id' => '',
                'object_increment_id' => self::INCREMENT_ID,
            ]))
            ->build();
        $objectManager->get(AppendReservationsInterface::class)->execute([$reservation]);
    }
}
