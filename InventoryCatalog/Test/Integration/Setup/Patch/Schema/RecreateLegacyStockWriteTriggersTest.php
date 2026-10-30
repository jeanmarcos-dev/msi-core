<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration\Setup\Patch\Schema;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteTriggers;
use Magento\InventoryCatalog\Setup\Patch\Schema\RecreateLegacyStockWriteTriggers;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class RecreateLegacyStockWriteTriggersTest extends TestCase
{
    /**
     * @magentoDbIsolation disabled
     */
    public function testApplyReplacesTheTriggersOfAnOlderRelease(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $expected = $this->definitions($connection);
        self::assertNotEmpty($expected);

        foreach (array_keys($expected) as $name) {
            $table = (string)$connection->fetchOne(
                'SELECT event_object_table FROM information_schema.triggers '
                . 'WHERE trigger_schema = DATABASE() AND trigger_name = ?',
                [$name]
            );
            $operation = str_ends_with($name, '_insert') ? 'INSERT' : 'UPDATE';
            $connection->query('DROP TRIGGER ' . $name);
            $connection->query(
                'CREATE TRIGGER ' . $name . ' AFTER ' . $operation . ' ON ' . $table . ' FOR EACH ROW '
                . 'INSERT INTO inventory_legacy_stock_write (table_name, operation, write_count, '
                . 'sample_product_id, first_detected_at, last_detected_at) '
                . "VALUES ('" . $table . "', '" . strtolower($operation) . "', 1, NEW.product_id, NOW(), NOW()) "
                . 'ON DUPLICATE KEY UPDATE write_count = write_count + 1, '
                . 'sample_product_id = NEW.product_id, last_detected_at = NOW()'
            );
        }
        self::assertNotEquals($expected, $this->definitions($connection));

        $objectManager->create(RecreateLegacyStockWriteTriggers::class)->apply();

        self::assertEquals($expected, $this->definitions($connection));
    }

    public function testItRunsAfterTheTriggersAreFirstInstalled(): void
    {
        self::assertContains(
            \Magento\InventoryCatalog\Setup\Patch\Schema\DetectLegacyStockWrites::class,
            RecreateLegacyStockWriteTriggers::getDependencies()
        );
        self::assertTrue(
            Bootstrap::getObjectManager()->get(LegacyStockWriteTriggers::class)->areInstalled()
        );
    }

    private function definitions($connection): array
    {
        return $connection->fetchPairs(
            'SELECT trigger_name, action_statement FROM information_schema.triggers '
            . "WHERE trigger_schema = DATABASE() AND trigger_name LIKE '%\\_msi\\_detect\\_%' ORDER BY trigger_name"
        );
    }
}
