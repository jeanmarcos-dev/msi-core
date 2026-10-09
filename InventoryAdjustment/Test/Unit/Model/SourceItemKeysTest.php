<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\InventoryAdjustment\Model\SourceItemKeys;
use PHPUnit\Framework\TestCase;

class SourceItemKeysTest extends TestCase
{
    public function testEverySkuInEverySource(): void
    {
        self::assertSame(
            [
                ['source_code' => 'a', 'sku' => '1001'],
                ['source_code' => 'b', 'sku' => '1001'],
                ['source_code' => 'a', 'sku' => 'X'],
                ['source_code' => 'b', 'sku' => 'X'],
            ],
            (new SourceItemKeys())->forSkusInSources([1001, 'X'], ['a', 'b'])
        );
    }

    public function testRowsGiveTheirSourceAndSku(): void
    {
        self::assertSame(
            [['source_code' => 'a', 'sku' => '7']],
            (new SourceItemKeys())->fromRows([['source_code' => 'a', 'sku' => 7, 'quantity' => '3']])
        );
    }
}
