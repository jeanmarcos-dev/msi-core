<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemsData;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetStockItemsDataTest extends TestCase
{
    private const STOCK_ID = 1;

    /**
     * @var ResourceConnection|MockObject
     */
    private ResourceConnection $resourceMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private AdapterInterface $connectionMock;

    /**
     * @var Select|MockObject
     */
    private Select $selectMock;

    /**
     * @var StockIndexTableNameResolverInterface|MockObject
     */
    private StockIndexTableNameResolverInterface $stockIndexTableNameResolverMock;

    /**
     * @var GetStockItemsData
     */
    private GetStockItemsData $getStockItemsData;

    /**
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->selectMock = $this->createMock(Select::class);
        $this->stockIndexTableNameResolverMock = $this->createMock(StockIndexTableNameResolverInterface::class);

        $this->resourceMock->method('getConnection')->willReturn($this->connectionMock);
        $this->connectionMock->method('select')->willReturn($this->selectMock);

        $this->getStockItemsData = new GetStockItemsData(
            $this->resourceMock,
            $this->stockIndexTableNameResolverMock
        );
    }

    /**
     * Ensure every stock, the default one included, is read from its index table with the SKUs bound.
     *
     * @param int $stockId
     * @return void
     * @throws LocalizedException
     */
    #[DataProvider('stockIdDataProvider')]
    public function testExecuteCallsFetchAllWithBindingParameter(int $stockId): void
    {
        $skus = ['sku1', 'sku2'];
        $indexTable = 'inventory_stock_' . $stockId;

        $this->stockIndexTableNameResolverMock->expects($this->once())
            ->method('execute')
            ->with($stockId)
            ->willReturn($indexTable);
        $this->selectMock->expects($this->once())
            ->method('from')
            ->with($indexTable, $this->anything())
            ->willReturnSelf();
        $this->selectMock->expects($this->once())
            ->method('where')
            ->with('sku IN (:sku0,:sku1)')
            ->willReturnSelf();

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->identicalTo($this->selectMock),
                $this->equalTo(self::buildExpectedBind($skus))
            )
            ->willReturn([
                ['sku' => 'sku1', 'quantity' => 5, 'is_salable' => 1],
                ['sku' => 'sku2', 'quantity' => 0, 'is_salable' => 0],
            ]);

        $result = $this->getStockItemsData->execute($skus, $stockId);

        $this->assertSame(
            [
                GetStockItemsDataInterface::QUANTITY => 5,
                GetStockItemsDataInterface::IS_SALABLE => 1,
            ],
            $result['sku1']
        );
        $this->assertSame(
            [
                GetStockItemsDataInterface::QUANTITY => 0,
                GetStockItemsDataInterface::IS_SALABLE => 0,
            ],
            $result['sku2']
        );
    }

    /**
     * @return array
     */
    public static function stockIdDataProvider(): array
    {
        return [
            'default stock' => [self::STOCK_ID],
            'custom stock' => [self::STOCK_ID + 1],
        ];
    }

    /**
     * Build the expected fetchAll() binding array the way GetStockItemsData::execute() does:
     * each SKU gets its own indexed placeholder (sku0, sku1, ...).
     *
     * @param string[] $skus
     * @return array
     */
    private static function buildExpectedBind(array $skus): array
    {
        $keys = array_map(static fn (int $i): string => 'sku' . $i, array_keys($skus));

        return array_combine($keys, $skus);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteOmitsSkusMissingFromTheIndex(): void
    {
        $skus = ['sku1', 'sku2'];

        $this->selectMock->method('from')->willReturnSelf();
        $this->selectMock->method('where')->willReturnSelf();
        $this->resourceMock->expects($this->never())->method('getTableName');
        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->identicalTo($this->selectMock),
                $this->equalTo(self::buildExpectedBind($skus))
            )
            ->willReturn([['sku' => 'SKU1', 'quantity' => 3, 'is_salable' => 1]]);

        $result = $this->getStockItemsData->execute($skus, self::STOCK_ID);

        $this->assertSame(
            ['sku1' => [GetStockItemsDataInterface::QUANTITY => 3, GetStockItemsDataInterface::IS_SALABLE => 1]],
            $result
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteWrapsConnectionExceptionInLocalizedException(): void
    {
        $skus = ['sku1'];

        $this->selectMock->method('from')->willReturnSelf();
        $this->selectMock->method('where')->willReturnSelf();

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->identicalTo($this->selectMock),
                $this->equalTo(self::buildExpectedBind($skus))
            )
            ->willThrowException(new \Exception('DB error'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not receive Stock Item data');

        $this->getStockItemsData->execute($skus, self::STOCK_ID);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testWithEmptySkus(): void
    {
        $this->resourceMock->expects($this->never())->method('getConnection');
        $this->assertEmpty($this->getStockItemsData->execute([], self::STOCK_ID));
    }
}
