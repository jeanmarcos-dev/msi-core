<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Cron;

use Magento\InventorySales\Cron\ReconcileReservations;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\InventorySales\Model\SourceReservation\ReconciliationConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReconcileReservationsTest extends TestCase
{
    /**
     * @var ReconciliationConfig|MockObject
     */
    private $config;

    /**
     * @var ReconcileReservationsSweep|MockObject
     */
    private $sweep;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ReconcileReservations
     */
    private $cron;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ReconciliationConfig::class);
        $this->sweep = $this->createMock(ReconcileReservationsSweep::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cron = new ReconcileReservations(
            $this->config,
            $this->sweep,
            $this->logger
        );
    }

    public function testSkipsWhenSweepDisabled(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(false);
        $this->sweep->expects(self::never())->method('execute');

        $this->cron->execute();
    }

    public function testRunsSweepWhenEnabled(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $this->sweep->expects(self::once())->method('execute')
            ->willReturn($this->sweepResult([]));
        $this->logger->expects(self::never())->method('info');
        $this->logger->expects(self::never())->method('notice');

        $this->cron->execute();
    }

    public function testLogsTheAbandonedPlacementsItRepaired(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $this->sweep->method('execute')->willReturn($this->sweepResult(['abandoned' => 2, 'compensations' => 3]));
        $this->logger->expects(self::once())->method('info')->with(
            self::stringContains('abandoned placements'),
            ['abandoned' => 2]
        );

        $this->cron->execute();
    }

    public function testNoticesAbandonedPlacementsLeftForTheNextRun(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $this->sweep->method('execute')->willReturn($this->sweepResult(['abandoned_pending' => 1]));
        $this->logger->expects(self::once())->method('notice')->with(
            self::stringContains('next run'),
            ['abandoned_pending' => 1]
        );

        $this->cron->execute();
    }

    public function testStaysQuietAboutPendingPlacementsUntilAMarkIsEffective(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $this->sweep->method('execute')->willReturn(
            $this->sweepResult(['abandoned_pending' => 1, 'mark_effective' => false])
        );
        $this->logger->expects(self::never())->method('notice');

        $this->cron->execute();
    }

    /**
     * @param array $overrides
     * @return array
     */
    private function sweepResult(array $overrides): array
    {
        return $overrides + [
            'orders' => 0,
            'abandoned' => 0,
            'abandoned_pending' => 0,
            'mark_effective' => true,
            'compensations' => 0,
            'stock_ids' => [],
            'limit_reached' => false,
        ];
    }
}
