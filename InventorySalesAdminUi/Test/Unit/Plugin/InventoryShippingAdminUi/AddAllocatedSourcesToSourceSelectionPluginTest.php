<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Test\Unit\Plugin\InventoryShippingAdminUi;

use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\SourceReservation\DistributeCompensationToSources;
use Magento\InventorySalesAdminUi\Plugin\InventoryShippingAdminUi\AddAllocatedSourcesToSourceSelectionPlugin;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\SourceSelectionDataProvider;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddAllocatedSourcesToSourceSelectionPluginTest extends TestCase
{
    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $config;

    /**
     * @var DistributeCompensationToSources|MockObject
     */
    private $distribute;

    /**
     * @var AddAllocatedSourcesToSourceSelectionPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(SourceReservationsConfig::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(3);
        $order->method('getIncrementId')->willReturn('000000042');
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->with(42)->willReturn($order);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(3)->willReturn($store);
        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(5);
        $stockResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockResolver->method('execute')->with(1)->willReturn($stock);
        $this->distribute = $this->createMock(DistributeCompensationToSources::class);

        $this->plugin = new AddAllocatedSourcesToSourceSelectionPlugin(
            $this->config,
            $orderRepository,
            $storeManager,
            $stockResolver,
            $this->distribute
        );
    }

    public function testAddsTheSourcesEachItemIsReservedAt(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->distribute->method('execute')->willReturnMap([
            [['SLR-1' => 2.0], 5, '000000042', ['SLR-1' => [['source_code' => 'slr_b', 'quantity' => 2.0]]]],
            [['SLR-3' => 3.0], 5, '000000042', ['SLR-3' => [
                ['source_code' => 'slr_a', 'quantity' => 1.0],
                ['source_code' => null, 'quantity' => 2.0],
            ]]],
        ]);

        $result = $this->plugin->afterGetData(
            $this->createMock(SourceSelectionDataProvider::class),
            $this->data()
        );

        self::assertSame(
            [['sourceCode' => 'slr_b', 'sourceName' => 'Source B', 'qty' => 2.0]],
            $result[42]['items'][0]['allocatedSources']
        );
        self::assertSame(
            [['sourceCode' => 'slr_a', 'sourceName' => 'Source A', 'qty' => 1.0]],
            $result[42]['items'][1]['allocatedSources']
        );
    }

    public function testLeavesTheDataUntouchedWhenSourceReservationsAreDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->distribute->expects(self::never())->method('execute');

        self::assertSame(
            $this->data(),
            $this->plugin->afterGetData($this->createMock(SourceSelectionDataProvider::class), $this->data())
        );
    }

    private function data(): array
    {
        return [
            42 => [
                'items' => [
                    ['orderItemId' => '7', 'sku' => 'SLR-1', 'qtyToShip' => 2.0],
                    ['orderItemId' => '8', 'sku' => 'SLR-3', 'qtyToShip' => 3.0],
                ],
                'order_id' => 42,
                'sourceCodes' => [
                    ['value' => 'slr_a', 'label' => 'Source A'],
                    ['value' => 'slr_b', 'label' => 'Source B'],
                ],
            ],
        ];
    }
}
