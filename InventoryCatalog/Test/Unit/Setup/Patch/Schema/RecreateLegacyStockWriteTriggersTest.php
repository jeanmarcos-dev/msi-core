<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Setup\Patch\Schema;

use Exception;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteTriggers;
use Magento\InventoryCatalog\Setup\Patch\Schema\DetectLegacyStockWrites;
use Magento\InventoryCatalog\Setup\Patch\Schema\RecreateLegacyStockWriteTriggers;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RecreateLegacyStockWriteTriggersTest extends TestCase
{
    /**
     * @var LegacyStockWriteTriggers|MockObject
     */
    private $triggers;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var RecreateLegacyStockWriteTriggers
     */
    private $patch;

    protected function setUp(): void
    {
        $this->triggers = $this->createMock(LegacyStockWriteTriggers::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->patch = new RecreateLegacyStockWriteTriggers($this->triggers, $this->logger);
    }

    public function testApplyInstallsTheTriggersAgain(): void
    {
        $this->triggers->expects(self::once())->method('install');
        $this->logger->expects(self::never())->method('warning');

        self::assertSame($this->patch, $this->patch->apply());
    }

    public function testApplyLogsInsteadOfFailingTheUpgradeWhenTheDatabaseRefuses(): void
    {
        $this->triggers->method('install')->willThrowException(new Exception('no TRIGGER privilege'));
        $this->logger->expects(self::once())->method('warning')->with(self::stringContains('no TRIGGER privilege'));

        self::assertSame($this->patch, $this->patch->apply());
    }

    public function testItDependsOnTheFirstInstallation(): void
    {
        self::assertSame([DetectLegacyStockWrites::class], RecreateLegacyStockWriteTriggers::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }
}
