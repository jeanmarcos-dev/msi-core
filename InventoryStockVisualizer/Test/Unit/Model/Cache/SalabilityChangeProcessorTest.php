<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\InventoryIndexer\Model\GetProductsIdsToProcess;
use Magento\InventoryStockVisualizer\Model\Cache\FlushStockVisualizerCache;
use Magento\InventoryStockVisualizer\Model\Cache\GetEnabledStoreIds;
use Magento\InventoryStockVisualizer\Model\Cache\SalabilityChangeProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SalabilityChangeProcessorTest extends TestCase
{
    private const BEFORE = ['SKU-1' => [1 => true]];
    private const AFTER = ['SKU-1' => [1 => false]];

    /**
     * @var GetEnabledStoreIds|MockObject
     */
    private $enabledStores;

    /**
     * @var GetProductsIdsToProcess|MockObject
     */
    private $getProductsIdsToProcess;

    /**
     * @var FlushStockVisualizerCache|MockObject
     */
    private $flush;

    /**
     * @var SalabilityChangeProcessor
     */
    private $processor;

    protected function setUp(): void
    {
        $this->enabledStores = $this->createMock(GetEnabledStoreIds::class);
        $this->getProductsIdsToProcess = $this->createMock(GetProductsIdsToProcess::class);
        $this->flush = $this->createMock(FlushStockVisualizerCache::class);
        $this->processor = new SalabilityChangeProcessor(
            $this->enabledStores,
            $this->getProductsIdsToProcess,
            $this->flush,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testAPanelEnabledOnlyOnAStoreViewPurgesTheFlippedProducts(): void
    {
        $this->enabledStores->method('execute')->willReturn([2]);
        $this->getProductsIdsToProcess->method('execute')->with(self::BEFORE, self::AFTER)->willReturn(['7']);
        $this->flush->expects($this->once())->method('execute')->with([7]);

        $this->processor->process(self::BEFORE, self::AFTER);
    }

    public function testNothingIsPurgedWhenNoStoreShowsThePanel(): void
    {
        $this->enabledStores->method('execute')->willReturn([]);
        $this->getProductsIdsToProcess->expects($this->never())->method('execute');
        $this->flush->expects($this->never())->method('execute');

        $this->processor->process(self::BEFORE, self::AFTER);
    }
}
