<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Indexer\SourceItem;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStock;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStockFactory;
use Magento\InventoryIndexer\Indexer\Stock\SkuListsProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexSkusInSourcesTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var SkuListsProcessor|MockObject
     */
    private $skuListsProcessor;

    /**
     * @var array
     */
    private $sourceFilter = [];

    /**
     * @var ReindexSkusInSources
     */
    private $model;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('distinct')->willReturnSelf();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($condition, $value = null) use ($select) {
            $this->sourceFilter = [$condition, $value];
            return $select;
        });
        $this->connection->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $skuListInStockFactory = $this->getMockBuilder(SkuListInStockFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $skuListInStockFactory->method('create')
            ->willReturnCallback(fn (array $data) => new SkuListInStock($data['stockId'], $data['skuList']));

        $this->skuListsProcessor = $this->createMock(SkuListsProcessor::class);

        $this->model = new ReindexSkusInSources($resourceConnection, $skuListInStockFactory, $this->skuListsProcessor);
    }

    public function testReindexesTheSkusInEveryStockLinkedToTheSources(): void
    {
        $this->connection->method('fetchCol')->willReturn(['5', '6']);

        $reindexed = [];
        $this->skuListsProcessor->expects(self::once())->method('reindexList')
            ->willReturnCallback(function (array $skuListInStockList) use (&$reindexed) {
                foreach ($skuListInStockList as $skuListInStock) {
                    $reindexed[$skuListInStock->getStockId()] = $skuListInStock->getSkuList();
                }
            });

        $this->model->execute(['SKU-1', 'SKU-2', 'SKU-1'], ['slr_a', 'slr_b', 'slr_a']);

        self::assertSame(['source_code IN (?)', ['slr_a', 'slr_b']], $this->sourceFilter);
        self::assertSame(
            [
                5 => ['SKU-1' => 'SKU-1', 'SKU-2' => 'SKU-2'],
                6 => ['SKU-1' => 'SKU-1', 'SKU-2' => 'SKU-2'],
            ],
            $reindexed
        );
    }

    public function testDoesNothingWhenTheSourcesBelongToNoStock(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);

        $this->skuListsProcessor->expects(self::never())->method('reindexList');

        $this->model->execute(['SKU-1'], ['unassigned']);
    }

    public function testDoesNothingWithoutSkusOrSources(): void
    {
        $this->connection->expects(self::never())->method('fetchCol');
        $this->skuListsProcessor->expects(self::never())->method('reindexList');

        $this->model->execute([], ['slr_a']);
        $this->model->execute(['SKU-1'], []);
    }
}
