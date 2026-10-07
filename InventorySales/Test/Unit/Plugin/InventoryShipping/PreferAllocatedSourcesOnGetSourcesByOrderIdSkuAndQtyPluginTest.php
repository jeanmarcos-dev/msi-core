<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryShipping;

use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\SourceReservation\DistributeCompensationToSources;
use Magento\InventorySales\Plugin\InventoryShipping\PreferAllocatedSourcesOnGetSourcesByOrderIdSkuAndQtyPlugin;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\GetSourcesByOrderIdSkuAndQty;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreferAllocatedSourcesOnGetSourcesByOrderIdSkuAndQtyPluginTest extends TestCase
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
     * @var array
     */
    private $proceeded = [];

    /**
     * @var PreferAllocatedSourcesOnGetSourcesByOrderIdSkuAndQtyPlugin
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

        $this->plugin = new PreferAllocatedSourcesOnGetSourcesByOrderIdSkuAndQtyPlugin(
            $this->config,
            $orderRepository,
            $storeManager,
            $stockResolver,
            $this->distribute
        );
    }

    public function testSuggestsTheSourceTheOrderReservedInsteadOfThePhysicalPriority(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->distribute->method('execute')->with(['SLR-3' => 2.0], 5, '000000042')
            ->willReturn(['SLR-3' => [['source_code' => 'slr_b', 'quantity' => 2.0]]]);

        $result = $this->suggest(2.0, [2.0 => ['slr_a' => 2.0, 'slr_b' => 0.0]]);

        self::assertSame(['slr_a' => 0.0, 'slr_b' => 2.0], $this->deductions($result));
        self::assertSame([2.0], $this->proceeded);
    }

    public function testCompletesTheUnallocatedRemainderWithTheSourceSelection(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->distribute->method('execute')->willReturn(['SLR-3' => [
            ['source_code' => 'slr_b', 'quantity' => 1.0],
            ['source_code' => null, 'quantity' => 2.0],
        ]]);

        $result = $this->suggest(3.0, [
            3.0 => ['slr_a' => 3.0, 'slr_b' => 0.0],
            2.0 => ['slr_a' => 2.0, 'slr_b' => 0.0],
        ]);

        self::assertSame(['slr_a' => 2.0, 'slr_b' => 1.0], $this->deductions($result));
    }

    public function testMovesAnAllocationToAnUnlistedSourceIntoTheRemainder(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->distribute->method('execute')->willReturn(['SLR-3' => [
            ['source_code' => 'slr_off', 'quantity' => 1.0],
            ['source_code' => 'slr_b', 'quantity' => 1.0],
        ]]);

        $result = $this->suggest(2.0, [
            2.0 => ['slr_a' => 2.0, 'slr_b' => 0.0],
            1.0 => ['slr_a' => 1.0, 'slr_b' => 0.0],
        ]);

        self::assertSame(['slr_a' => 1.0, 'slr_b' => 1.0], $this->deductions($result));
    }

    public function testKeepsTheSourceSelectionWithoutAnAllocation(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->distribute->method('execute')
            ->willReturn(['SLR-3' => [['source_code' => null, 'quantity' => 2.0]]]);

        $result = $this->suggest(2.0, [2.0 => ['slr_a' => 2.0, 'slr_b' => 0.0]]);

        self::assertSame(['slr_a' => 2.0, 'slr_b' => 0.0], $this->deductions($result));
    }

    public function testKeepsTheSourceSelectionWhenSourceReservationsAreDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->distribute->expects(self::never())->method('execute');

        $result = $this->suggest(2.0, [2.0 => ['slr_a' => 2.0, 'slr_b' => 0.0]]);

        self::assertSame(['slr_a' => 2.0, 'slr_b' => 0.0], $this->deductions($result));
    }

    private function suggest(float $qty, array $selectionsByQty): array
    {
        $proceed = function (int $orderId, string $sku, float $requested) use ($selectionsByQty) {
            $this->proceeded[] = $requested;
            $rows = [];
            foreach ($selectionsByQty[(string)$requested] as $sourceCode => $qtyToDeduct) {
                $rows[] = [
                    'sourceName' => strtoupper($sourceCode),
                    'sourceCode' => $sourceCode,
                    'qtyAvailable' => 3.0,
                    'qtyToDeduct' => $qtyToDeduct,
                ];
            }
            return $rows;
        };

        return $this->plugin->aroundExecute(
            $this->createMock(GetSourcesByOrderIdSkuAndQty::class),
            $proceed,
            42,
            'SLR-3',
            $qty
        );
    }

    private function deductions(array $rows): array
    {
        return array_column($rows, 'qtyToDeduct', 'sourceCode');
    }
}
