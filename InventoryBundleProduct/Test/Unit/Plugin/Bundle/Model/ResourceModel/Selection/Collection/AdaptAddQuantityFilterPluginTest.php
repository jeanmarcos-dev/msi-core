<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProduct\Test\Unit\Plugin\Bundle\Model\ResourceModel\Selection\Collection;

use Magento\Bundle\Model\ResourceModel\Selection\Collection;
use Magento\Framework\DB\Select;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryBundleProduct\Plugin\Bundle\Model\ResourceModel\Selection\Collection\AdaptAddQuantityFilterPlugin;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdaptAddQuantityFilterPluginTest extends TestCase
{
    private const STOCK_ID = 1;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var Collection|MockObject
     */
    private $collection;

    /**
     * @var AreProductsSalableInterface|MockObject
     */
    private $areProductsSalable;

    /**
     * @var AdaptAddQuantityFilterPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getSelect', 'resetData'])
            ->getMock();
        $this->collection->method('getSelect')->willReturn($this->select);
        $this->collection->method('getData')->willReturn(
            [['sku' => 'a'], ['sku' => 'b'], ['sku' => 'c']]
        );

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturn($website);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(self::STOCK_ID);
        $stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockByWebsiteIdResolver->method('execute')->willReturn($stock);

        $this->areProductsSalable = $this->createMock(AreProductsSalableInterface::class);

        $this->plugin = new AdaptAddQuantityFilterPlugin(
            $this->areProductsSalable,
            $storeManager,
            $stockByWebsiteIdResolver
        );
    }

    /**
     * @param array $salabilityBySku
     * @return void
     */
    private function expectSalability(array $salabilityBySku): void
    {
        $results = [];
        foreach ($salabilityBySku as $sku => $isSalable) {
            $result = $this->createMock(IsProductSalableResultInterface::class);
            $result->method('getSku')->willReturn((string)$sku);
            $result->method('isSalable')->willReturn($isSalable);
            $results[] = $result;
        }
        $this->areProductsSalable->expects(self::once())->method('execute')
            ->with(['a', 'b', 'c'], self::STOCK_ID)->willReturn($results);
    }

    /**
     * The legacy filter is never reached: it inner joins a table MSI no longer writes.
     */
    public function testItNeverFallsBackToTheLegacyFilter(): void
    {
        $this->expectSalability(['a' => true, 'b' => true, 'c' => true]);
        $this->select->expects(self::never())->method('where');

        $proceedCalls = 0;
        $this->plugin->aroundAddQuantityFilter(
            $this->collection,
            function () use (&$proceedCalls) {
                $proceedCalls++;

                return $this->collection;
            }
        );

        self::assertSame(0, $proceedCalls);
    }

    public function testItExcludesEverySkuThatIsNotSalable(): void
    {
        $this->expectSalability(['a' => false, 'b' => true, 'c' => false]);
        $this->select->expects(self::once())->method('where')
            ->with('e.sku NOT IN(?)', ['a', 'c'])
            ->willReturn($this->select);

        $this->collection->expects(self::once())->method('resetData');

        self::assertSame(
            $this->collection,
            $this->plugin->aroundAddQuantityFilter($this->collection, fn () => $this->collection)
        );
    }
}
