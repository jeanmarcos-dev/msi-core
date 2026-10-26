<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\InventoryStockVisualizer\Model\Cache\DispatchPurge;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see DispatchPurge
 */
class DispatchPurgeTest extends TestCase
{
    /**
     * @var IndexerRegistry|MockObject
     */
    private $indexerRegistry;

    /**
     * @var PublisherInterface|MockObject
     */
    private $publisher;

    /**
     * @var CacheInterface|MockObject
     */
    private $cache;

    /**
     * @var PurgeBySkus|MockObject
     */
    private $purgeBySkus;

    /**
     * @var DispatchPurge
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->indexerRegistry = $this->createMock(IndexerRegistry::class);
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->purgeBySkus = $this->createMock(PurgeBySkus::class);

        $this->model = new DispatchPurge(
            $this->indexerRegistry,
            $this->publisher,
            $this->cache,
            $this->purgeBySkus
        );
    }

    /**
     * An empty list neither flushes nor publishes.
     *
     * @return void
     */
    public function testEmptyDoesNothing(): void
    {
        $this->purgeBySkus->expects($this->never())->method('execute');
        $this->publisher->expects($this->never())->method('publish');

        $this->model->execute(['', '']);
    }

    /**
     * On-save indexing flushes the fragment inline and de-duplicates the SKUs.
     *
     * @return void
     */
    public function testOnSaveIndexingFlushesInline(): void
    {
        $this->indexerRegistry->method('get')->willReturn($this->indexer(false));
        $this->purgeBySkus->expects($this->once())->method('execute')->with(['SKU-1']);
        $this->publisher->expects($this->never())->method('publish');

        $this->model->execute(['SKU-1', 'SKU-1']);
    }

    /**
     * Scheduled indexing publishes and sets the coalescing guard.
     *
     * @return void
     */
    public function testScheduledIndexingPublishesAndGuards(): void
    {
        $this->indexerRegistry->method('get')->willReturn($this->indexer(true));
        $this->cache->method('load')->willReturn(false);
        $this->purgeBySkus->expects($this->never())->method('execute');
        $this->publisher->expects($this->once())->method('publish')
            ->with(DispatchPurge::TOPIC, 'SKU-1');
        $this->cache->expects($this->once())->method('save')
            ->with('1', DispatchPurge::GUARD_PREFIX . 'SKU-1', $this->callback('is_array'), $this->greaterThan(0));

        $this->model->execute(['SKU-1']);
    }

    /**
     * A pending guard coalesces the burst: no second message is published.
     *
     * @return void
     */
    public function testPendingGuardCoalesces(): void
    {
        $this->indexerRegistry->method('get')->willReturn($this->indexer(true));
        $this->cache->method('load')->willReturn('1');
        $this->publisher->expects($this->never())->method('publish');
        $this->cache->expects($this->never())->method('save');

        $this->model->execute(['SKU-1']);
    }

    /**
     * An indexer lookup failure degrades to an inline flush rather than dropping the purge.
     *
     * @return void
     */
    public function testIndexerFailureFlushesInline(): void
    {
        $this->indexerRegistry->method('get')->willThrowException(new \RuntimeException('boom'));
        $this->purgeBySkus->expects($this->once())->method('execute')->with(['SKU-1']);
        $this->publisher->expects($this->never())->method('publish');

        $this->model->execute(['SKU-1']);
    }

    /**
     * @param bool $scheduled
     * @return IndexerInterface|MockObject
     */
    private function indexer(bool $scheduled)
    {
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->method('isScheduled')->willReturn($scheduled);

        return $indexer;
    }
}
