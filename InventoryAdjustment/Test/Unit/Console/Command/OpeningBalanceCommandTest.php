<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Console\Command;

use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\InventoryAdjustment\Console\Command\OpeningBalanceCommand;
use Magento\InventoryAdjustment\Model\OpeningBalance;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class OpeningBalanceCommandTest extends TestCase
{
    /**
     * @var OpeningBalance|MockObject
     */
    private $openingBalance;

    protected function setUp(): void
    {
        $this->openingBalance = $this->createMock(OpeningBalance::class);
    }

    public function testReportsHowManyRowsItWroteAndTheirRequestId(): void
    {
        $this->openingBalance->expects(self::once())
            ->method('write')
            ->with('3f2c-opening', 1000)
            ->willReturn(3);
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('3 opening balance row(s)', $tester->getDisplay());
        self::assertStringContainsString('3f2c-opening', $tester->getDisplay());
    }

    public function testPassesTheBatchSize(): void
    {
        $this->openingBalance->expects(self::once())->method('write')->with('3f2c-opening', 250)->willReturn(0);

        self::assertSame(Command::SUCCESS, $this->tester()->execute(['--batch-size' => '250']));
    }

    public function testRejectsABatchSizeBelowOne(): void
    {
        $this->openingBalance->expects(self::never())->method('write');
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['--batch-size' => '0']));
        self::assertStringContainsString('--batch-size', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $identityGenerator = $this->createMock(IdentityGeneratorInterface::class);
        $identityGenerator->method('generateId')->willReturn('3f2c-opening');

        return new CommandTester(new OpeningBalanceCommand($this->openingBalance, $identityGenerator));
    }
}
