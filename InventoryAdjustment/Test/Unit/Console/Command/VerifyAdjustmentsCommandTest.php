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

    private function tester(array $drift): CommandTester
    {
        $getDrift = $this->createMock(GetAdjustmentDrift::class);
        $getDrift->method('execute')->willReturn($drift);

        return new CommandTester(new VerifyAdjustmentsCommand($getDrift));
    }
}
