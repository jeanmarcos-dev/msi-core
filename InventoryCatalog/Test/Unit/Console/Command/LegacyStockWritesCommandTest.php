<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Console\Command;

use Magento\InventoryCatalog\Console\Command\LegacyStockWritesCommand;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteLog;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteTriggers;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class LegacyStockWritesCommandTest extends TestCase
{
    /**
     * @var LegacyStockWriteLog|MockObject
     */
    private $legacyStockWriteLog;

    /**
     * @var LegacyStockWriteTriggers|MockObject
     */
    private $legacyStockWriteTriggers;

    /**
     * @var CommandTester
     */
    private $tester;

    protected function setUp(): void
    {
        $this->legacyStockWriteLog = $this->createMock(LegacyStockWriteLog::class);
        $this->legacyStockWriteTriggers = $this->createMock(LegacyStockWriteTriggers::class);
        $this->legacyStockWriteTriggers->method('areInstalled')->willReturn(true);
        $this->tester = new CommandTester(
            new LegacyStockWritesCommand($this->legacyStockWriteLog, $this->legacyStockWriteTriggers)
        );
    }

    public function testReturnsSuccessWhenNothingWasDetected(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn([]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('No writes recorded', $this->tester->getDisplay());
    }

    public function testReturnsFailureAndReportsTheDetectedWrites(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn(
            [
                [
                    'table_name' => 'cataloginventory_stock_item',
                    'operation' => 'update',
                    'write_count' => '7',
                    'sample_product_id' => '15',
                    'first_detected_at' => '2026-08-09 10:00:00',
                    'last_detected_at' => '2026-08-09 11:00:00',
                ],
            ]
        );

        $exit = $this->tester->execute([]);
        $display = $this->tester->getDisplay();

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('cataloginventory_stock_item', $display);
        self::assertStringContainsString('SourceItemsSaveInterface', $display);
    }

    public function testWarnsWhenDetectionIsNotActive(): void
    {
        $triggers = $this->createMock(LegacyStockWriteTriggers::class);
        $triggers->method('areInstalled')->willReturn(false);
        $this->legacyStockWriteLog->method('getDetections')->willReturn([]);
        $tester = new CommandTester(new LegacyStockWritesCommand($this->legacyStockWriteLog, $triggers));

        $tester->execute([]);

        self::assertStringContainsString('Detection is not active', $tester->getDisplay());
    }

    public function testClearEmptiesTheLog(): void
    {
        $this->legacyStockWriteLog->expects(self::once())->method('clear');

        $exit = $this->tester->execute(['--clear' => true]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    public function testEnableInstallsTheTriggers(): void
    {
        $this->legacyStockWriteTriggers->expects(self::once())->method('install');

        $exit = $this->tester->execute(['--enable' => true]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    public function testDisableDropsTheTriggers(): void
    {
        $this->legacyStockWriteTriggers->expects(self::once())->method('remove');

        $exit = $this->tester->execute(['--disable' => true]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    public function testEnableAndDisableAreMutuallyExclusive(): void
    {
        $this->legacyStockWriteTriggers->expects(self::never())->method('install');
        $this->legacyStockWriteTriggers->expects(self::never())->method('remove');

        $exit = $this->tester->execute(['--enable' => true, '--disable' => true]);

        self::assertSame(Command::FAILURE, $exit);
    }
}
