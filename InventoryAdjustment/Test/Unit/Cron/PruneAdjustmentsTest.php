<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\InventoryAdjustment\Cron\PruneAdjustments;
use Magento\InventoryAdjustment\Model\Config;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentPruner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PruneAdjustmentsTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var AdjustmentPruner|MockObject
     */
    private $pruner;

    /**
     * @var DateTime|MockObject
     */
    private $dateTime;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->pruner = $this->createMock(AdjustmentPruner::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('gmtTimestamp')->willReturn(gmmktime(2, 30, 0, 10, 10, 2026));
    }

    public function testKeepsEveryRowWhenTheRetentionIsZero(): void
    {
        $this->config->method('getRetentionDays')->willReturn(0);
        $this->pruner->expects(self::never())->method('prune');

        $this->cron()->execute();
    }

    public function testPrunesRowsOlderThanTheRetentionInUtc(): void
    {
        $this->config->method('getRetentionDays')->willReturn(90);
        $this->config->method('getPruneBatchSize')->willReturn(250);
        $this->pruner->expects(self::once())
            ->method('prune')
            ->with('2026-07-12 02:30:00', 250)
            ->willReturn(3);

        $this->cron()->execute();
    }

    public function testPrunesWithAValidCutoffWhenTheRetentionIsTheCap(): void
    {
        $this->config->method('getRetentionDays')->willReturn(36500);
        $this->config->method('getPruneBatchSize')->willReturn(100000);
        $this->pruner->expects(self::once())
            ->method('prune')
            ->with('1926-11-04 02:30:00', 100000)
            ->willReturn(0);

        $this->cron()->execute();
    }

    private function cron(): PruneAdjustments
    {
        return new PruneAdjustments($this->config, $this->pruner, $this->dateTime);
    }
}
