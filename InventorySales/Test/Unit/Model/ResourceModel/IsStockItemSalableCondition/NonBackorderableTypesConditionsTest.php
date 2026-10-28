<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel\IsStockItemSalableCondition;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryConfigurationApi\Model\GetNonBackorderableProductTypesInterface;
use Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition\BackordersCondition;
use Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition\GetNonBackorderableTypeCondition;
use Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition\MinQtyStockCondition;
use Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition\MinQtyStockWithReservationsCondition;
use PHPUnit\Framework\TestCase;

class NonBackorderableTypesConditionsTest extends TestCase
{
    private const LISTED = "product.type_id IN ('virtual', 'downloadable')";

    public function testBackordersAreNotEnabledForTheListedTypes(): void
    {
        $sql = (new BackordersCondition(
            $this->configuration(1, -5.0),
            $this->typeCondition(['virtual', 'downloadable'])
        ))->execute($this->select());

        self::assertStringContainsString('(product.type_id IS NULL OR NOT (' . self::LISTED . '))', $sql);
    }

    public function testMinQtyWithReservationsIsNeverNegativeForTheListedTypes(): void
    {
        $sql = (new MinQtyStockWithReservationsCondition(
            $this->configuration(1, -5.0),
            $this->typeCondition(['virtual', 'downloadable'])
        ))->execute($this->select());

        $minQty = 'IF(stock_item_configuration.use_config_min_qty = 1, -5, stock_item_configuration.min_qty)';
        self::assertStringContainsString(
            'IF(' . self::LISTED . ', GREATEST(' . $minQty . ', 0), ' . $minQty . ')',
            $sql
        );
    }

    public function testMinQtyIsNeverNegativeForTheListedTypes(): void
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection());

        $sql = (new MinQtyStockCondition(
            $this->configuration(1, -5.0),
            $resourceConnection,
            $this->typeCondition(['virtual', 'downloadable'])
        ))->execute($this->select());

        self::assertStringContainsString('> IF(' . self::LISTED . ', 0, -5))', $sql);
        self::assertStringContainsString(
            '> IF(' . self::LISTED . ', GREATEST(stock_item_configuration.min_qty, 0), '
            . 'stock_item_configuration.min_qty))',
            $sql
        );
    }

    public function testAnEmptyListLeavesTheConditionsUnchanged(): void
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection());
        $conditions = [
            new BackordersCondition($this->configuration(1, -5.0), $this->typeCondition([])),
            new MinQtyStockWithReservationsCondition($this->configuration(1, -5.0), $this->typeCondition([])),
            new MinQtyStockCondition($this->configuration(1, -5.0), $resourceConnection, $this->typeCondition([])),
        ];

        foreach ($conditions as $condition) {
            self::assertStringNotContainsString('product.type_id', $condition->execute($this->select()));
        }
    }

    private function typeCondition(array $types): GetNonBackorderableTypeCondition
    {
        $getTypes = $this->createStub(GetNonBackorderableProductTypesInterface::class);
        $getTypes->method('execute')->willReturn($types);

        return new GetNonBackorderableTypeCondition($getTypes);
    }

    private function configuration(int $backorders, float $minQty): StockConfigurationInterface
    {
        $configuration = $this->createStub(StockConfigurationInterface::class);
        $configuration->method('getBackorders')->willReturn($backorders);
        $configuration->method('getMinQty')->willReturn($minQty);

        return $configuration;
    }

    private function select(): Select
    {
        $select = $this->createStub(Select::class);
        $select->method('getConnection')->willReturn($this->connection());

        return $select;
    }

    private function connection(): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('getCheckSql')
            ->willReturnCallback(fn ($condition, $true, $false) => "IF($condition, $true, $false)");
        $connection->method('quoteInto')
            ->willReturnCallback(fn (string $text, array $values) => str_replace(
                '?',
                implode(', ', array_map(fn ($value) => "'$value'", $values)),
                $text
            ));

        return $connection;
    }
}
