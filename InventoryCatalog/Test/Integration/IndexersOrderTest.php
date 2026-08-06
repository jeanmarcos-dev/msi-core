<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Framework\Indexer\Config\Converter\SortingAdjustmentInterface;
use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceIndexer;
use Magento\CatalogInventory\Model\Indexer\Stock\Processor as StockIndexer;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;

class IndexersOrderTest extends TestCase
{
    /**
     * @var SortingAdjustmentInterface
     */
    private $sortingAdjustment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sortingAdjustment = Bootstrap::getObjectManager()->create(SortingAdjustmentInterface::class);
    }

    /**
     * @return void
     */
    public function testIndexersOrder()
    {
        $output = $this->sortingAdjustment->adjust($this->buildIndexerList());
        $this->assertArrayHasKey(PriceIndexer::INDEXER_ID, $output);
        $this->assertArrayHasKey(InventoryIndexer::INDEXER_ID, $output);
        $order = array_keys($output);
        $inventoryPos = array_search(InventoryIndexer::INDEXER_ID, $order);
        $pricePos = array_search(PriceIndexer::INDEXER_ID, $order);
        $this->assertTrue($inventoryPos < $pricePos);
    }

    /**
     * The legacy stock indexer no longer produces data, so hoisting it in front of the MSI indexer
     * would only spend a full catalog pass on an inert action.
     *
     * @return void
     */
    public function testLegacyStockIndexerKeepsItsConfiguredPosition()
    {
        $unAdjusted = $this->buildIndexerList();
        $expectedPosition = array_search(StockIndexer::INDEXER_ID, array_keys($unAdjusted));

        $order = array_keys($this->sortingAdjustment->adjust($unAdjusted));

        $this->assertNotSame(StockIndexer::INDEXER_ID, $order[0]);
        $this->assertSame($expectedPosition, array_search(StockIndexer::INDEXER_ID, $order));
    }

    /**
     * @return array
     */
    private function buildIndexerList(): array
    {
        return [
            'indexer1' => [],
            PriceIndexer::INDEXER_ID => [],
            'indexer2' => [],
            InventoryIndexer::INDEXER_ID => [],
            'indexer3' => [],
            StockIndexer::INDEXER_ID => [],
            'indexer4' => []
        ];
    }
}
