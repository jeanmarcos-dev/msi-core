<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteTriggers;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LegacyStockWriteTriggersTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var LegacyStockWriteTriggers
     */
    private $triggers;

    /**
     * @var array
     */
    private $queries = [];

    protected function setUp(): void
    {
        $this->queries = [];
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('quoteIdentifier')->willReturnCallback(
            static fn ($value) => '`' . $value . '`'
        );
        $this->connection->method('quote')->willReturnCallback(
            static fn ($value) => "'" . $value . "'"
        );
        $this->connection->method('query')->willReturnCallback(
            function ($sql) {
                $this->queries[] = $sql;

                return null;
            }
        );

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->triggers = new LegacyStockWriteTriggers($resourceConnection);
    }

    public function testInstallCreatesOneTriggerPerTableAndOperation(): void
    {
        $this->triggers->install();

        $created = array_values(array_filter($this->queries, static fn ($sql) => str_starts_with($sql, 'CREATE')));
        self::assertCount(4, $created);
        foreach (LegacyStockWriteTriggers::WATCHED_TABLES as $table) {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $name = $table . '_msi_detect_' . strtolower($operation);
                self::assertNotEmpty(
                    array_filter(
                        $created,
                        static fn ($sql) => str_contains($sql, '`' . $name . '`')
                            && str_contains($sql, 'AFTER ' . $operation . ' ON `' . $table . '`')
                    ),
                    'No trigger created for ' . $name
                );
            }
        }
    }

    public function testInstallDropsThePreviousTriggerFirst(): void
    {
        $this->triggers->install();

        $dropped = array_filter($this->queries, static fn ($sql) => str_starts_with($sql, 'DROP TRIGGER IF EXISTS'));
        self::assertCount(4, $dropped);
        self::assertStringStartsWith('DROP TRIGGER IF EXISTS', $this->queries[0]);
        self::assertStringStartsWith('CREATE TRIGGER', $this->queries[1]);
    }

    public function testInstalledTriggerRecordsTheWriteAgainstTheLogTable(): void
    {
        $this->triggers->install();

        $sql = $this->queries[1];
        self::assertStringContainsString('INSERT INTO `inventory_legacy_stock_write`', $sql);
        self::assertStringContainsString("VALUES ('cataloginventory_stock_item', 'insert'", $sql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE write_count = write_count + 1', $sql);
        self::assertStringContainsString('NEW.product_id', $sql);
    }

    public function testRemoveDropsEveryTrigger(): void
    {
        $this->triggers->remove();

        self::assertCount(4, $this->queries);
        foreach ($this->queries as $sql) {
            self::assertStringStartsWith('DROP TRIGGER IF EXISTS', $sql);
        }
    }

    public function testAreInstalledIsTrueWhenEveryTriggerIsPresent(): void
    {
        $this->connection->method('fetchOne')->willReturn('1');

        self::assertTrue($this->triggers->areInstalled());
    }

    public function testAreInstalledIsFalseWhenOneTriggerIsMissing(): void
    {
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('1', '1', '0', '1');

        self::assertFalse($this->triggers->areInstalled());
    }
}
