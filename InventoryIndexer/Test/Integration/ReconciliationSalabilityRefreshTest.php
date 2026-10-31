<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Test\Fixture\DeleteSourceItems as DeleteSourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;
use PHPUnit\Framework\TestCase;

class ReconciliationSalabilityRefreshTest extends TestCase
{
    private const SKU = 'sibling-shipment-sku';

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    public static function setUpBeforeClass(): void
    {
        Bootstrap::getObjectManager()->get(ClearQueueProcessor::class)
            ->execute('inventory.reservations.updateSalabilityStatus');
    }

    protected function setUp(): void
    {
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Config('cataloginventory/source_reservations/enabled', '1'),
        Config('cataloginventory/source_reservations/reconcile_cancel_refund', '1'),
        DataFixture(SourceFixture::class, as: 'sourceA'),
        DataFixture(SourceFixture::class, as: 'sourceB'),
        DataFixture(StockFixture::class, as: 'mainStock'),
        DataFixture(StockFixture::class, as: 'siblingStock'),
        DataFixture(
            StockSourceLinksFixture::class,
            [
                ['stock_id' => '$mainStock.stock_id$', 'source_code' => '$sourceA.source_code$', 'priority' => 1],
                ['stock_id' => '$mainStock.stock_id$', 'source_code' => '$sourceB.source_code$', 'priority' => 2],
                ['stock_id' => '$siblingStock.stock_id$', 'source_code' => '$sourceA.source_code$', 'priority' => 1],
            ]
        ),
        DataFixture(
            StockSalesChannelsFixture::class,
            ['stock_id' => '$mainStock.stock_id$', 'sales_channels' => ['base']]
        ),
        DataFixture(ProductFixture::class, ['sku' => self::SKU], 'product'),
        DataFixture(DeleteSourceItemsFixture::class, [['sku' => '$product.sku$', 'source_code' => 'default']]),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => '$product.sku$', 'source_code' => '$sourceA.source_code$', 'quantity' => 3],
                ['sku' => '$product.sku$', 'source_code' => '$sourceB.source_code$', 'quantity' => 2],
            ]
        ),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(
            AddProductToCartFixture::class,
            ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 3]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order'),
    ]
    public function testReconcilingAnOrderCancelledOutsideTheFlowRefreshesEveryStockSharingItsSource(): void
    {
        $this->processSalabilityUpdates();
        $this->cancelOutsideTheFlow();
        $this->processSalabilityUpdates();

        $this->assertBothStocksMatchAFullReindex();
    }

    #[
        DbIsolation(false),
        AppIsolation(true),
        Config('cataloginventory/source_reservations/enabled', '1'),
        Config('cataloginventory/source_reservations/reconcile_cancel_refund', '1'),
        DataFixture(SourceFixture::class, as: 'sourceA'),
        DataFixture(SourceFixture::class, as: 'sourceB'),
        DataFixture(StockFixture::class, as: 'mainStock'),
        DataFixture(StockFixture::class, as: 'siblingStock'),
        DataFixture(
            StockSourceLinksFixture::class,
            [
                ['stock_id' => '$mainStock.stock_id$', 'source_code' => '$sourceA.source_code$', 'priority' => 1],
                ['stock_id' => '$mainStock.stock_id$', 'source_code' => '$sourceB.source_code$', 'priority' => 2],
                ['stock_id' => '$siblingStock.stock_id$', 'source_code' => '$sourceA.source_code$', 'priority' => 1],
            ]
        ),
        DataFixture(
            StockSalesChannelsFixture::class,
            ['stock_id' => '$mainStock.stock_id$', 'sales_channels' => ['base']]
        ),
        DataFixture(ProductFixture::class, ['sku' => self::SKU], 'product'),
        DataFixture(DeleteSourceItemsFixture::class, [['sku' => '$product.sku$', 'source_code' => 'default']]),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => '$product.sku$', 'source_code' => '$sourceA.source_code$', 'quantity' => 3],
                ['sku' => '$product.sku$', 'source_code' => '$sourceB.source_code$', 'quantity' => 2],
            ]
        ),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(
            AddProductToCartFixture::class,
            ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 3]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order'),
    ]
    public function testTheReconciliationSweepRefreshesEveryStockSharingTheSourceOfTheOrder(): void
    {
        $this->processSalabilityUpdates();
        $this->markCancelledWithoutObservers();
        Bootstrap::getObjectManager()->get(ReconcileReservationsSweep::class)->execute(500);
        $this->processSalabilityUpdates();

        $this->assertBothStocksMatchAFullReindex();
    }

    private function assertBothStocksMatchAFullReindex(): void
    {
        $mainStockId = $this->getStockId('mainStock');
        $siblingStockId = $this->getStockId('siblingStock');
        $mainRow = $this->getIndexRow($mainStockId);
        $siblingRow = $this->getIndexRow($siblingStockId);
        $this->reindex([$mainStockId, $siblingStockId]);

        self::assertSame($this->getIndexRow($mainStockId), $mainRow);
        self::assertSame($this->getIndexRow($siblingStockId), $siblingRow);
        self::assertSame(['quantity' => '3.0000', 'is_salable' => '1'], $siblingRow);
    }

    private function cancelOutsideTheFlow(): void
    {
        $order = Bootstrap::getObjectManager()->get(OrderFactory::class)->create();
        $order->loadByIncrementId($this->fixtures->get('order')->getIncrementId());
        $order->setState(Order::STATE_CANCELED)->setStatus(Order::STATE_CANCELED);
        $order->save();
    }

    private function markCancelledWithoutObservers(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $connection->update(
            $connection->getTableName('sales_order'),
            ['state' => Order::STATE_CANCELED, 'status' => Order::STATE_CANCELED],
            ['entity_id = ?' => (int)$this->fixtures->get('order')->getId()]
        );
    }

    private function getStockId(string $alias): int
    {
        $stock = $this->fixtures->get($alias);

        return (int)$stock->getStockId();
    }

    private function processSalabilityUpdates(): void
    {
        Bootstrap::getObjectManager()->get(ConsumerFactory::class)
            ->get('inventory.reservations.updateSalabilityStatus')
            ->process(10);
    }

    private function reindex(array $stockIds): void
    {
        Bootstrap::getObjectManager()->get(StockIndexer::class)->executeList($stockIds);
    }

    private function getIndexRow(int $stockId): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $table = $objectManager->get(StockIndexTableNameResolverInterface::class)->execute($stockId);

        return $connection->fetchRow(
            $connection->select()->from($table, ['quantity', 'is_salable'])->where('sku = ?', self::SKU)
        );
    }
}
