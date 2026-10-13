<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Console\Command;

use Magento\InventoryAdjustment\Console\Command\VerifyAdjustmentsCommand;
use Magento\InventoryAdjustment\Model\ResourceModel\GetAdjustmentDrift;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class VerifyAdjustmentsCommandTest extends TestCase
{
    public function testPassesWhenEveryItemMatchesItsHistory(): void
    {
        $tester = $this->tester([]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No drift', $tester->getDisplay());
    }

    public function testFailsAndListsTheItemsThatDrifted(): void
    {
        $tester = $this->tester([
            ['source_code' => 'src_a', 'sku' => 'SKU-1', 'quantity_after' => '5.0000', 'quantity' => '7.0000'],
        ]);

        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '50']));
        self::assertStringContainsString('SKU-1', $tester->getDisplay());
        self::assertStringContainsString('1 source item(s)', $tester->getDisplay());
    }

    public function testReportsTheCountWhenTheDriftIsBelowTheLimit(): void
    {
        $tester = $this->tester($this->drift(3));

        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '20']));
        self::assertStringContainsString(
            '3 source item(s) changed without an adjustment row.',
            $tester->getDisplay()
        );
        self::assertStringNotContainsString('there may be more', $tester->getDisplay());
    }

    public function testSaysThereMayBeMoreWhenTheDriftReachesTheLimit(): void
    {
        $tester = $this->tester($this->drift(20));

        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '20']));
        self::assertStringContainsString(
            'Showing the first 20 source items with drift; there may be more. Raise --limit to see them.',
            $tester->getDisplay()
        );
        self::assertStringNotContainsString('changed without an adjustment row', $tester->getDisplay());
    }

    private function drift(int $count): array
    {
        $drift = [];
        for ($i = 1; $i <= $count; $i++) {
            $drift[] = [
                'source_code' => 'src_a',
                'sku' => 'SKU-' . $i,
                'quantity_after' => '5.0000',
                'quantity' => '7.0000',
            ];
        }

        return $drift;
    }

    private function tester(array $drift): CommandTester
    {
        $getDrift = $this->createMock(GetAdjustmentDrift::class);
        $getDrift->method('execute')->willReturn($drift);

        return new CommandTester(new VerifyAdjustmentsCommand($getDrift));
    }
}
