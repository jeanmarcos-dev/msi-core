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

    private function configWithPageSize(?string $value): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('cataloginventory/source_item_adjustment/max_page_size')
            ->willReturn($value);

        return new Config($scopeConfig);
    }
}
