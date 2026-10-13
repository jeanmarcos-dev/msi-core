<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryAdjustment\Model\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testReadsTheEnabledFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('cataloginventory/source_item_adjustment/enabled')
            ->willReturn(false);

        self::assertFalse((new Config($scopeConfig))->isEnabled());
    }

    public function testReadsTheMaximumPageSize(): void
    {
        self::assertSame(200, $this->configWithPageSize('200')->getMaxPageSize());
    }

    public function testAnUnusableMaximumPageSizeFallsBackTo500(): void
    {
        foreach ([null, '0', '-3', 'many'] as $value) {
            self::assertSame(500, $this->configWithPageSize($value)->getMaxPageSize());
        }
    }

    public function testReadsTheRetentionDays(): void
    {
        self::assertSame(90, $this->configWith('retention_days', '90')->getRetentionDays());
    }

    public function testAnUnusableRetentionKeepsTheHistoryForever(): void
    {
        foreach ([null, '', '-5', 'forever'] as $value) {
            self::assertSame(0, $this->configWith('retention_days', $value)->getRetentionDays());
        }
    }

    public function testReadsThePruneBatchSize(): void
    {
        self::assertSame(2, $this->configWith('prune_batch_size', '2')->getPruneBatchSize());
    }

    public function testAnUnusablePruneBatchSizeFallsBackTo5000(): void
    {
        foreach ([null, '0', '-3', 'many'] as $value) {
            self::assertSame(5000, $this->configWith('prune_batch_size', $value)->getPruneBatchSize());
        }
    }

    public function testCapsTheRetentionAt36500Days(): void
    {
        self::assertSame(36500, $this->configWith('retention_days', '36500')->getRetentionDays());
        self::assertSame(36500, $this->configWith('retention_days', '36501')->getRetentionDays());
        self::assertSame(36500, $this->configWith('retention_days', '9223372036854775807')->getRetentionDays());
    }

    public function testATwentyDigitRetentionIsTheCapWithoutOverflow(): void
    {
        foreach (['99999999999999999999', '00000000000000000000099999', '1e30', '+99999999999999999999'] as $value) {
            self::assertSame(36500, $this->configWith('retention_days', $value)->getRetentionDays());
        }
    }

    public function testCapsThePruneBatchSizeAt100000(): void
    {
        self::assertSame(100000, $this->configWith('prune_batch_size', '100000')->getPruneBatchSize());
        self::assertSame(100000, $this->configWith('prune_batch_size', '10000000')->getPruneBatchSize());
        self::assertSame(100000, $this->configWith('prune_batch_size', '99999999999999999999')->getPruneBatchSize());
    }

    private function configWithPageSize(?string $value): Config
    {
        return $this->configWith('max_page_size', $value);
    }

    private function configWith(string $field, ?string $value): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('cataloginventory/source_item_adjustment/' . $field)
            ->willReturn($value);

        return new Config($scopeConfig);
    }
}
