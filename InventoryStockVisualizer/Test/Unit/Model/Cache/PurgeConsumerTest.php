<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\InventoryStockVisualizer\Model\Cache\DispatchPurge;
use Magento\InventoryStockVisualizer\Model\Cache\GetEnabledStoreIds;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeBySkus;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeConsumer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see PurgeConsumer
 */
class PurgeConsumerTest extends TestCase
{
    private const SKU = 'SKU-1';

    /**
     * @var PurgeBySkus|MockObject
     */
    private $purgeBySkus;

    /**
     * @var CacheInterface|MockObject
     */
    private $cache;

    /**
     * @var GetEnabledStoreIds|MockObject
     */
    private $enabledStores;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var PurgeConsumer
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->purgeBySkus = $this->createMock(PurgeBySkus::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->enabledStores = $this->createMock(GetEnabledStoreIds::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->model = new PurgeConsumer($this->purgeBySkus, $this->cache, $this->enabledStores, $this->logger);
    }

    /**
     * Processing clears the coalescing guard and purges the SKU.
     *
     * @return void
     */
    public function testClearsGuardAndPurges(): void
    {
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->cache->expects($this->once())->method('remove')->with(DispatchPurge::GUARD_PREFIX . self::SKU);
        $this->purgeBySkus->expects($this->once())->method('execute')->with([self::SKU]);

        $this->model->process(self::SKU);
    }

    public function testAPanelEnabledOnlyOnAStoreViewStillPurges(): void
    {
        $this->enabledStores->method('execute')->willReturn([2]);
        $this->purgeBySkus->expects($this->once())->method('execute')->with([self::SKU]);

        $this->model->process(self::SKU);
    }

    /**
     * The guard is still reopened when the feature is disabled, but nothing is purged.
     *
     * @return void
     */
    public function testDisabledClearsGuardWithoutPurging(): void
    {
        $this->enabledStores->method('execute')->willReturn([]);
        $this->cache->expects($this->once())->method('remove')->with(DispatchPurge::GUARD_PREFIX . self::SKU);
        $this->purgeBySkus->expects($this->never())->method('execute');

        $this->model->process(self::SKU);
    }

    /**
     * A failing purge is swallowed and logged so the message is not retried forever.
     *
     * @return void
     */
    public function testSwallowsAndLogsFailure(): void
    {
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->purgeBySkus->method('execute')->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())->method('error');

        $this->model->process(self::SKU);
    }
}
