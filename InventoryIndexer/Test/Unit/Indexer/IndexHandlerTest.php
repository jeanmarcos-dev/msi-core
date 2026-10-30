<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Indexer;

use ArrayIterator;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\InventoryApi\Model\CacheInterface;
use Magento\InventoryIndexer\Indexer\IndexHandler;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(IndexHandler::class),
]
class IndexHandlerTest extends TestCase
{
    private const CONNECTION_NAME = 'indexer';
    private const TABLE_NAME = 'inventory_stock_1';

    /**
     * @var AdapterInterface|MockObject
     */
    private $connectionMock;

    /**
     * @var CacheInterface|MockObject
     */
    private $stockItemDataCacheMock;

    /**
     * @var IndexName|Stub
     */
    private $indexNameMock;

    /**
     * @var string[]
     */
    private $calls = [];

    /**
     * @var IndexHandler
     */
    private $indexHandler;

    protected function setUp(): void
    {
        $this->calls = [];
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->stockItemDataCacheMock = $this->createMock(CacheInterface::class);
        $this->indexNameMock = $this->createStub(IndexName::class);

        $resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $resourceConnectionMock->expects($this->atLeastOnce())
            ->method('getConnection')
            ->with(self::CONNECTION_NAME)
            ->willReturn($this->connectionMock);
        $indexNameResolverMock = $this->createStub(IndexNameResolverInterface::class);
        $indexNameResolverMock->method('resolveName')->willReturn(self::TABLE_NAME);
        $deploymentConfigMock = $this->createStub(DeploymentConfig::class);
        $deploymentConfigMock->method('get')->willReturn(null);

        $this->indexHandler = new IndexHandler(
            $indexNameResolverMock,
            new Batch(),
            $resourceConnectionMock,
            100,
            $deploymentConfigMock,
            $this->stockItemDataCacheMock
        );
    }

    public function testSaveIndexDropsTheCachedStockItemDataOfTheSavedSkusForEveryStock(): void
    {
        $this->connectionMock->expects($this->once())->method('insertOnDuplicate')->willReturnCallback(function () {
            $this->calls[] = 'insert';
            return 2;
        });
        $this->stockItemDataCacheMock->expects($this->once())
            ->method('clean')
            ->with(['SKU-1', 'SKU-2'], null)
            ->willReturnCallback(function () {
                $this->calls[] = 'clean';
            });

        $this->indexHandler->saveIndex(
            $this->indexNameMock,
            new ArrayIterator([
                ['sku' => 'SKU-1', 'quantity' => 5.0, 'is_salable' => 1],
                ['sku' => 'SKU-2', 'quantity' => 0.0, 'is_salable' => 0],
            ]),
            self::CONNECTION_NAME
        );

        $this->assertSame(['insert', 'clean'], $this->calls);
    }

    public function testCleanIndexDropsTheCachedStockItemDataOfTheDeletedSkusForEveryStock(): void
    {
        $this->connectionMock->expects($this->once())->method('delete')->willReturnCallback(function () {
            $this->calls[] = 'delete';
            return 2;
        });
        $this->stockItemDataCacheMock->expects($this->once())
            ->method('clean')
            ->with(['SKU-1', 'SKU-2'], null)
            ->willReturnCallback(function () {
                $this->calls[] = 'clean';
            });

        $this->indexHandler->cleanIndex(
            $this->indexNameMock,
            new ArrayIterator(['SKU-1', 'SKU-2']),
            self::CONNECTION_NAME
        );

        $this->assertSame(['delete', 'clean'], $this->calls);
    }

    public function testSaveIndexWithoutDocumentsLeavesTheCacheAlone(): void
    {
        $this->connectionMock->expects($this->never())->method('insertOnDuplicate');
        $this->stockItemDataCacheMock->expects($this->never())->method('clean');

        $this->indexHandler->saveIndex($this->indexNameMock, new ArrayIterator([]), self::CONNECTION_NAME);
    }
}
