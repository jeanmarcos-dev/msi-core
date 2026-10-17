<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Console\Command;

use Magento\InventorySales\Console\Command\ReconcileReservationsCommand;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ReconcileReservationsCommandTest extends TestCase
{
    /**
     * @var ReconcileReservationsSweep|MockObject
     */
    private $sweep;

    /**
     * @var CommandTester
     */
    private $tester;

    protected function setUp(): void
    {
        $this->sweep = $this->createMock(ReconcileReservationsSweep::class);
        $this->tester = new CommandTester(new ReconcileReservationsCommand($this->sweep));
    }

    public function testReportsTerminalOrdersAndAbandonedPlacementsApart(): void
    {
        $this->sweep->expects(self::once())->method('execute')->with(500, false)
            ->willReturn(
                $this->sweepResult(['orders' => 2, 'abandoned' => 1, 'compensations' => 4, 'stock_ids' => [5]])
            );

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString(
            'Reconciled 2 order(s), 1 abandoned placement(s), 4 compensation(s) across stock(s) 5.',
            $this->tester->getDisplay()
        );
        self::assertStringNotContainsString('too recent', $this->tester->getDisplay());
    }

    public function testExplainsAbandonedPlacementsThatAreTooRecentToRepair(): void
    {
        $this->sweep->method('execute')->with(500, true)->willReturn($this->sweepResult(['abandoned_pending' => 2]));

        $this->tester->execute(['--dry-run' => true]);

        $display = $this->tester->getDisplay();
        self::assertStringContainsString('Would reconcile 0 order(s), 0 abandoned placement(s)', $display);
        self::assertStringContainsString(
            '2 abandoned placement(s) are too recent to repair; a later run will repair them.',
            $display
        );
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
