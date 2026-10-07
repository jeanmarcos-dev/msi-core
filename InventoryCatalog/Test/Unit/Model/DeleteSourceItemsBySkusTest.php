<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;
use Magento\InventoryCatalog\Model\DeleteSourceItemsBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeleteSourceItemsBySkusTest extends TestCase
{
    /**
     * @var GetSourceItemsBySkuInterface|MockObject
     */
    private $getSourceItemsBySku;

    /**
     * @var SourceItemsDeleteInterface|MockObject
     */
    private $sourceItemsDelete;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var DeleteSourceItemsBySkus
     */
    private $model;

    protected function setUp(): void
    {
        $this->getSourceItemsBySku = $this->createMock(GetSourceItemsBySkuInterface::class);
        $this->sourceItemsDelete = $this->createMock(SourceItemsDeleteInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->model = new DeleteSourceItemsBySkus(
            $this->getSourceItemsBySku,
            $this->sourceItemsDelete,
            $this->logger
        );
    }

    public function testDeletesThroughTheServiceContractSoTheStockIndexIsCleaned(): void
    {
        $first = [$this->createMock(SourceItemInterface::class)];
        $second = [$this->createMock(SourceItemInterface::class), $this->createMock(SourceItemInterface::class)];
        $this->getSourceItemsBySku->method('execute')->willReturnMap([['SKU-1', $first], ['SKU-2', $second]]);

        $deleted = [];
        $this->sourceItemsDelete->expects(self::exactly(2))->method('execute')
            ->willReturnCallback(function (array $sourceItems) use (&$deleted) {
                $deleted[] = $sourceItems;
            });

        $this->model->execute(['SKU-1', 'SKU-2']);

        self::assertSame([$first, $second], $deleted);
    }

    public function testSkipsSkusWithoutSourceItems(): void
    {
        $this->getSourceItemsBySku->method('execute')->willReturn([]);

        $this->sourceItemsDelete->expects(self::never())->method('execute');

        $this->model->execute(['SKU-1']);
    }

    public function testLogsAFailureAndContinuesWithTheNextSku(): void
    {
        $sourceItems = [$this->createMock(SourceItemInterface::class)];
        $this->getSourceItemsBySku->method('execute')->willReturn($sourceItems);
        $this->sourceItemsDelete->expects(self::exactly(2))->method('execute')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('could not delete')),
                null
            );

        $this->logger->expects(self::once())->method('error')->with('could not delete');

        $this->model->execute(['SKU-1', 'SKU-2']);
    }
}
