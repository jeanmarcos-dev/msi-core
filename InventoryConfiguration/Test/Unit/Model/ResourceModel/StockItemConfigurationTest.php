<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StockItemConfigurationTest extends TestCase
{
    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceConnectionMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connectionMock;

    /**
     * @var StockItemConfiguration
     */
    private $model;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceConnectionMock->method('getTableName')
            ->willReturnCallback(static fn (string $name): string => 'prefix_' . $name);
        $this->model = new StockItemConfiguration($this->resourceConnectionMock);
    }

    public function testGetWithoutSkusDoesNotQuery(): void
    {
        $this->connectionMock->expects(self::never())->method('select');
        self::assertSame([], $this->model->get([]));
    }

    public function testGetIndexesRowsBySku(): void
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();
        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchAll')->willReturn([
            ['sku' => 'SKU-1', 'manage_stock' => 1],
            ['sku' => 'SKU-2', 'manage_stock' => 0],
        ]);

        self::assertSame(
            [
                'SKU-1' => ['sku' => 'SKU-1', 'manage_stock' => 1],
                'SKU-2' => ['sku' => 'SKU-2', 'manage_stock' => 0],
            ],
            $this->model->get(['SKU-1', 'SKU-2'])
        );
    }

    public function testSaveWithoutRowsDoesNotWrite(): void
    {
        $this->connectionMock->expects(self::never())->method('insertOnDuplicate');
        $this->model->save([]);
    }

    public function testSaveExcludesTheSkuFromTheUpdatedColumns(): void
    {
        $this->connectionMock->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'prefix_' . StockItemConfiguration::TABLE_NAME,
                [['sku' => 'SKU-1', 'manage_stock' => 1]],
                ['manage_stock']
            );

        $this->model->save([['sku' => 'SKU-1', 'manage_stock' => 1]]);
    }

    public function testSaveGroupsRowsByTheColumnsTheyCarry(): void
    {
        $calls = [];
        $this->connectionMock->expects(self::exactly(2))
            ->method('insertOnDuplicate')
            ->willReturnCallback(function ($table, $rows, $columns) use (&$calls) {
                $calls[] = [$rows, $columns];
                return 1;
            });

        $this->model->save([
            ['sku' => 'SKU-1', 'manage_stock' => 1],
            ['sku' => 'SKU-2', 'min_qty' => 5],
            ['sku' => 'SKU-3', 'manage_stock' => 0],
        ]);

        self::assertSame(
            [
                [[['sku' => 'SKU-1', 'manage_stock' => 1], ['sku' => 'SKU-3', 'manage_stock' => 0]], ['manage_stock']],
                [[['sku' => 'SKU-2', 'min_qty' => 5]], ['min_qty']],
            ],
            $calls
        );
    }

    public function testUpdateBySkusIgnoresEmptyInput(): void
    {
        $this->connectionMock->expects(self::never())->method('update');
        $this->model->updateBySkus([], ['manage_stock' => 1]);
        $this->model->updateBySkus(['SKU-1'], []);
    }

    public function testUpdateBySkus(): void
    {
        $this->connectionMock->expects(self::once())
            ->method('update')
            ->with(
                'prefix_' . StockItemConfiguration::TABLE_NAME,
                ['manage_stock' => 1],
                ['sku IN (?)' => ['SKU-1']]
            );

        $this->model->updateBySkus(['SKU-1'], ['manage_stock' => 1]);
    }
}
