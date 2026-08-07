<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryCatalog\Model\ResourceModel\GetStockQuantityBySkuList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetStockQuantityBySkuListTest extends TestCase
{
    private const STOCK_ID = 10;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var GetStockQuantityBySkuList
     */
    private $model;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        foreach (['from', 'joinInner', 'where', 'group', 'columns'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->model = new GetStockQuantityBySkuList($resourceConnection);
    }

    public function testItSumsTheQuantityOfEverySkuItIsAskedFor(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            ['sku' => 'SKU-1', 'quantity' => '8.5000'],
            ['sku' => 'SKU-2', 'quantity' => '0.0000'],
        ]);

        self::assertSame(
            ['SKU-1' => 8.5, 'SKU-2' => 0.0],
            $this->model->execute(['SKU-1', 'SKU-2'], self::STOCK_ID)
        );
    }

    public function testASkuWithoutSourceItemsIsAbsentFromTheResult(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);

        self::assertSame([], $this->model->execute(['SKU-1'], self::STOCK_ID));
    }

    public function testAnEmptySkuListIsAnsweredWithoutTouchingTheDatabase(): void
    {
        $this->connection->expects(self::never())->method('select');
        $this->connection->expects(self::never())->method('fetchAll');

        self::assertSame([], $this->model->execute([], self::STOCK_ID));
    }
}
