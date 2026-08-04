<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Indexer\Stock\Strategy;

use Magento\Indexer\Model\ProcessManager;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStock;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStockFactory;
use Magento\InventoryIndexer\Indexer\Stock\GetAllStockIds;
use Magento\InventoryIndexer\Indexer\Stock\IndexDataFiller;
use Magento\InventoryIndexer\Indexer\Stock\Strategy\Sync;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexTableSwitcherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SyncTest extends TestCase
{
    /**
     * @var IndexStructureInterface|MockObject
     */
    private $indexStructure;

    /**
     * @var IndexTableSwitcherInterface|MockObject
     */
    private $indexTableSwitcher;

    /**
     * @var ProcessManager|MockObject
     */
    private $processManager;

    /**
     * @var Sync
     */
    private $strategy;

    protected function setUp(): void
    {
        $this->indexStructure = $this->createMock(IndexStructureInterface::class);
        $this->indexStructure->method('isExist')->willReturn(true);
        $this->indexTableSwitcher = $this->createMock(IndexTableSwitcherInterface::class);
        $this->processManager = $this->createMock(ProcessManager::class);

        $indexNameBuilder = $this->createMock(IndexNameBuilder::class);
        foreach (['setIndexId', 'addDimension', 'setAlias'] as $method) {
            $indexNameBuilder->method($method)->willReturnSelf();
        }
        $indexNameBuilder->method('build')->willReturn($this->createMock(IndexName::class));

        $skuListInStockFactory = $this->createMock(SkuListInStockFactory::class);
        $skuListInStockFactory->method('create')->willReturn($this->createMock(SkuListInStock::class));

        $this->strategy = new Sync(
            $this->createMock(GetAllStockIds::class),
            $this->indexStructure,
            $indexNameBuilder,
            $this->indexTableSwitcher,
            $skuListInStockFactory,
            $this->createMock(IndexDataFiller::class),
            $this->processManager
        );
    }

    public function testReindexesMultipleStocksThroughProcessManager(): void
    {
        $executedFunctions = 0;
        $this->processManager->expects(self::once())
            ->method('execute')
            ->willReturnCallback(function (array $userFunctions) use (&$executedFunctions) {
                foreach ($userFunctions as $userFunction) {
                    $userFunction();
                    $executedFunctions++;
                }
            });

        $this->strategy->executeList([1, 2, 3]);

        self::assertSame(3, $executedFunctions);
    }

    public function testReindexesSingleStockWithoutProcessManager(): void
    {
        $this->processManager->expects(self::never())->method('execute');
        $this->indexTableSwitcher->expects(self::once())->method('switch');

        $this->strategy->executeList([2]);
    }

    public function testReindexesTheDefaultStockLikeAnyOther(): void
    {
        $this->processManager->expects(self::never())->method('execute');
        $this->indexTableSwitcher->expects(self::once())->method('switch');

        $this->strategy->executeList([1]);
    }
}
