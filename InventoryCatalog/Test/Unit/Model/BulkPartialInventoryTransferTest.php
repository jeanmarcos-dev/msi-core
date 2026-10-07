<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventoryCatalogApi\Model\PartialInventoryTransferValidatorInterface;
use Magento\InventoryCatalog\Model\BulkPartialInventoryTransfer;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BulkPartialInventoryTransferTest extends TestCase
{
    /**
     * @var TransferInventoryPartially|MockObject
     */
    private $transferCommand;

    /**
     * @var ReindexSkusInSources|MockObject
     */
    private $reindexSkusInSources;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var BulkPartialInventoryTransfer
     */
    private $model;

    protected function setUp(): void
    {
        $validationResult = $this->createMock(ValidationResult::class);
        $validationResult->method('isValid')->willReturn(true);
        $validator = $this->createMock(PartialInventoryTransferValidatorInterface::class);
        $validator->method('validate')->willReturn($validationResult);
        $this->transferCommand = $this->createMock(TransferInventoryPartially::class);
        $this->reindexSkusInSources = $this->createMock(ReindexSkusInSources::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);

        $this->model = new BulkPartialInventoryTransfer(
            $validator,
            $this->transferCommand,
            $this->reindexSkusInSources,
            $resourceConnection
        );
    }

    public function testReindexesTheTransferredSkusSoSalabilityChangesAreProcessed(): void
    {
        $this->reindexSkusInSources->expects(self::once())->method('execute')
            ->with(['SKU-1', 'SKU-2', 'SKU-1'], ['slr_a', 'slr_b']);

        $this->model->execute('slr_a', 'slr_b', $this->items(['SKU-1', 'SKU-2', 'SKU-1']));
    }

    public function testTransfersEveryItemInOneTransaction(): void
    {
        $calls = [];
        $this->connection->method('beginTransaction')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'begin';
            return $this->connection;
        });
        $this->transferCommand->method('execute')->willReturnCallback(
            function (PartialInventoryTransferItemInterface $item) use (&$calls) {
                $calls[] = $item->getSku();
            }
        );
        $this->connection->method('commit')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'commit';
            return $this->connection;
        });

        $this->model->execute('slr_a', 'slr_b', $this->items(['SKU-1', 'SKU-2']));

        self::assertSame(['begin', 'SKU-1', 'SKU-2', 'commit'], $calls);
    }

    public function testRollsBackEveryItemWhenOneFails(): void
    {
        $this->transferCommand->method('execute')->willReturnOnConsecutiveCalls(
            null,
            self::throwException(new ValidationException(__('not available')))
        );
        $this->connection->expects(self::once())->method('rollBack');
        $this->connection->expects(self::never())->method('commit');
        $this->reindexSkusInSources->expects(self::never())->method('execute');

        $this->expectException(ValidationException::class);

        $this->model->execute('slr_a', 'slr_b', $this->items(['SKU-1', 'SKU-2']));
    }

    private function items(array $skus): array
    {
        $items = [];
        foreach ($skus as $sku) {
            $item = $this->createMock(PartialInventoryTransferItemInterface::class);
            $item->method('getSku')->willReturn($sku);
            $items[] = $item;
        }
        return $items;
    }
}
