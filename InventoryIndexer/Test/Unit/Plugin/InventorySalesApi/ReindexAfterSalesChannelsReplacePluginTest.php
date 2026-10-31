<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventorySalesApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\StockReindexBatch;
use Magento\InventoryIndexer\Plugin\InventorySalesApi\ReindexAfterSalesChannelsReplacePlugin;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Model\ReplaceSalesChannelsForStockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexAfterSalesChannelsReplacePluginTest extends TestCase
{
    private const DEFAULT_STOCK_ID = 1;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var StockIndexer|MockObject
     */
    private $stockIndexer;

    /**
     * @var IndexerInterface|MockObject
     */
    private $inventoryIndexer;

    /**
     * @var ReplaceSalesChannelsForStockInterface|MockObject
     */
    private $subject;

    /**
     * @var StockReindexBatch
     */
    private StockReindexBatch $stockReindexBatch;

    /**
     * @var ReindexAfterSalesChannelsReplacePlugin
     */
    private ReindexAfterSalesChannelsReplacePlugin $plugin;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->stockIndexer = $this->createMock(StockIndexer::class);
        $defaultStockProvider = $this->createMock(DefaultStockProviderInterface::class);
        $defaultStockProvider->method('getId')->willReturn(self::DEFAULT_STOCK_ID);
        $this->inventoryIndexer = $this->createMock(IndexerInterface::class);
        $this->inventoryIndexer->method('isValid')->willReturn(true);
        $indexerRegistry = $this->createMock(IndexerRegistry::class);
        $indexerRegistry->method('get')->with(InventoryIndexer::INDEXER_ID)->willReturn($this->inventoryIndexer);
        $this->subject = $this->createMock(ReplaceSalesChannelsForStockInterface::class);
        $this->stockReindexBatch = new StockReindexBatch();

        $this->plugin = new ReindexAfterSalesChannelsReplacePlugin(
            $resourceConnection,
            $this->stockIndexer,
            $defaultStockProvider,
            $indexerRegistry,
            $this->stockReindexBatch
        );
    }

    public function testWebsiteAssignedToTheStockReindexesThatStock(): void
    {
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2]);

        $this->execute([$this->website('base')], 2, $this->proceedingOnce());
    }

    public function testWebsiteMovedFromAnotherStockReindexesBothStocks(): void
    {
        $this->connection->method('fetchPairs')->willReturn(['base' => '3']);
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2, 3]);

        $this->execute([$this->website('base')], 2, $this->proceedingOnce());
    }

    public function testWebsiteRemovedFromTheStockReindexesItAndTheDefaultStock(): void
    {
        $this->connection->method('fetchPairs')->willReturn(['base' => '2', 'eu' => '2']);
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2, self::DEFAULT_STOCK_ID]);

        $this->execute([$this->website('base')], 2, $this->proceedingOnce());
    }

    public function testUnchangedChannelsDoNotReindex(): void
    {
        $this->connection->method('fetchPairs')->willReturn(['base' => '2', 'eu' => '2']);
        $this->stockIndexer->expects(self::never())->method('executeList');

        $this->execute([$this->website('eu'), $this->website('base')], 2, $this->proceedingOnce());
    }

    public function testChannelsOtherThanWebsitesAreIgnored(): void
    {
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->stockIndexer->expects(self::never())->method('executeList');

        $this->execute([$this->channel('marketplace', 'marketplace')], 2, $this->proceedingOnce());
    }

    public function testStockIsReindexedOnlyAfterTheChannelsAreReplaced(): void
    {
        $this->connection->method('fetchPairs')->willReturn([]);
        $replaced = false;
        $this->stockIndexer->method('executeList')->willReturnCallback(function () use (&$replaced) {
            self::assertTrue($replaced);
        });

        $this->execute([$this->website('base')], 2, function () use (&$replaced) {
            $replaced = true;
        });
    }

    public function testNothingIsReindexedWhileTheInventoryIndexIsNotBuilt(): void
    {
        $inventoryIndexer = $this->createMock(IndexerInterface::class);
        $inventoryIndexer->method('isValid')->willReturn(false);
        $indexerRegistry = $this->createMock(IndexerRegistry::class);
        $indexerRegistry->method('get')->willReturn($inventoryIndexer);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $plugin = new ReindexAfterSalesChannelsReplacePlugin(
            $resourceConnection,
            $this->stockIndexer,
            $this->createMock(DefaultStockProviderInterface::class),
            $indexerRegistry,
            new StockReindexBatch()
        );
        $this->stockIndexer->expects(self::never())->method('executeList');
        $proceeded = 0;

        $plugin->aroundExecute($this->subject, function () use (&$proceeded) {
            $proceeded++;
        }, [$this->website('base')], 2);

        self::assertSame(1, $proceeded);
    }

    public function testChannelsReplacedDuringAStockSaveAreHeldForTheEndOfTheSave(): void
    {
        $this->connection->method('fetchPairs')->willReturn(['base' => '2', 'eu' => '2']);
        $this->stockIndexer->expects(self::never())->method('executeList');
        $this->stockReindexBatch->open();

        $this->execute([$this->website('base')], 2, $this->proceedingOnce());

        self::assertSame([2, self::DEFAULT_STOCK_ID], $this->stockReindexBatch->close());
    }

    public function testUnchangedChannelsDuringAStockSaveHoldNothing(): void
    {
        $this->connection->method('fetchPairs')->willReturn(['base' => '2']);
        $this->stockReindexBatch->open();

        $this->execute([$this->website('base')], 2, $this->proceedingOnce());

        self::assertSame([], $this->stockReindexBatch->close());
    }

    public function testChannelsReplacedAfterTheStockSaveEndedAreReindexedAtOnce(): void
    {
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->stockReindexBatch->open();
        $this->stockReindexBatch->close();
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2]);

        $this->execute([$this->website('base')], 2, $this->proceedingOnce());
    }

    public function testFailureOfTheReplacementDoesNotReindex(): void
    {
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->stockIndexer->expects(self::never())->method('executeList');

        $this->expectException(\RuntimeException::class);

        $this->execute([$this->website('base')], 2, function () {
            throw new \RuntimeException('replace failed');
        });
    }

    /**
     * Run the plugin the way the interceptor does.
     *
     * @param SalesChannelInterface[] $channels
     * @param int $stockId
     * @param callable $proceed
     * @return void
     */
    private function execute(array $channels, int $stockId, callable $proceed): void
    {
        $this->plugin->aroundExecute($this->subject, $proceed, $channels, $stockId);
    }

    /**
     * Build a proceed callable that must run exactly once.
     *
     * @return callable
     */
    private function proceedingOnce(): callable
    {
        $calls = 0;

        return function () use (&$calls) {
            self::assertSame(0, $calls++);
        };
    }

    /**
     * Build a website sales channel.
     *
     * @param string $code
     * @return SalesChannelInterface|MockObject
     */
    private function website(string $code)
    {
        return $this->channel($code, SalesChannelInterface::TYPE_WEBSITE);
    }

    /**
     * Build a sales channel of the given type.
     *
     * @param string $code
     * @param string $type
     * @return SalesChannelInterface|MockObject
     */
    private function channel(string $code, string $type)
    {
        $channel = $this->createMock(SalesChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getType')->willReturn($type);

        return $channel;
    }
}
