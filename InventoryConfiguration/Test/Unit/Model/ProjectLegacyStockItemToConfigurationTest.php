<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\InventoryConfiguration\Model\ProjectLegacyStockItemToConfiguration;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProjectLegacyStockItemToConfigurationTest extends TestCase
{
    /**
     * @var StockItemConfigurationResource|MockObject
     */
    private $resourceMock;

    /**
     * @var CacheStorage|MockObject
     */
    private $cacheStorageMock;

    /**
     * @var ProjectLegacyStockItemToConfiguration
     */
    private $model;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(StockItemConfigurationResource::class);
        $this->cacheStorageMock = $this->createMock(CacheStorage::class);
        $this->model = new ProjectLegacyStockItemToConfiguration($this->resourceMock, $this->cacheStorageMock);
    }

    public function testOnlyConfigurationColumnsPresentOnTheEntityAreProjected(): void
    {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn([
            'item_id' => 7,
            'product_id' => 3,
            'stock_id' => 1,
            'qty' => 12.0,
            'manage_stock' => 1,
            'is_in_stock' => 0,
        ]);

        $this->resourceMock->expects(self::once())
            ->method('save')
            ->with([['sku' => 'SKU-1', 'is_in_stock' => 0, 'manage_stock' => 1]]);

        $this->model->execute('SKU-1', $legacyStockItem);
    }

    public function testAbsentColumnsAreOmittedRatherThanNulled(): void
    {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn(['min_qty' => 4]);

        $this->resourceMock->expects(self::once())
            ->method('save')
            ->with([['sku' => 'SKU-1', 'min_qty' => 4]]);

        $this->model->execute('SKU-1', $legacyStockItem);
    }

    public function testCacheIsEvicted(): void
    {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn(['manage_stock' => 1]);

        $this->cacheStorageMock->expects(self::once())->method('delete')->with('SKU-1');

        $this->model->execute('SKU-1', $legacyStockItem);
    }

    /**
     * @param array $data
     * @param array<string,mixed> $originalData
     * @param bool $expected
     */
    #[DataProvider('indexRelevantChangeDataProvider')]
    public function testItReportsWhetherTheStockIndexHasToBeRebuilt(
        array $data,
        array $originalData,
        bool $expected
    ): void {
        $legacyStockItem = $this->createMock(AbstractModel::class);
        $legacyStockItem->method('getData')->willReturn($data);
        $legacyStockItem->method('getOrigData')
            ->willReturnCallback(static fn (string $field) => $originalData[$field] ?? null);

        self::assertSame($expected, $this->model->execute('SKU-1', $legacyStockItem));
    }

    /**
     * @return array
     */
    public static function indexRelevantChangeDataProvider(): array
    {
        return [
            'manage stock turned off' => [['manage_stock' => 0], ['manage_stock' => 1], true],
            'backorders allowed' => [['backorders' => 1], ['backorders' => 0], true],
            'the out of stock threshold moved' => [['min_qty' => 5], ['min_qty' => 0], true],
            'the configuration override was dropped' => [
                ['use_config_manage_stock' => 1],
                ['use_config_manage_stock' => 0],
                true,
            ],
            'a first projection with nothing to compare against' => [['manage_stock' => 1], [], true],
            'the same value written again' => [['manage_stock' => 1], ['manage_stock' => 1], false],
            'a numeric string against its number' => [['min_qty' => '4'], ['min_qty' => '4'], false],
            'a field the index never reads' => [['max_sale_qty' => 9], ['max_sale_qty' => 1], false],
            'nothing at all' => [[], [], false],
        ];
    }
}
