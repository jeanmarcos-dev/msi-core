<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Indexer\SourceItem;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemIds;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexSourceItemsBySkusTest extends TestCase
{
    /**
     * @var GetSourceItemsBySkuInterface|MockObject
     */
    private $getSourceItemsBySku;

    /**
     * @var GetSourceItemIds|MockObject
     */
    private $getSourceItemIds;

    /**
     * @var SourceItemIndexer|MockObject
     */
    private $sourceItemIndexer;

    /**
     * @var ReindexSourceItemsBySkus
     */
    private $model;

    protected function setUp(): void
    {
        $this->getSourceItemsBySku = $this->createMock(GetSourceItemsBySkuInterface::class);
        $this->getSourceItemIds = $this->createMock(GetSourceItemIds::class);
        $this->sourceItemIndexer = $this->createMock(SourceItemIndexer::class);

        $this->model = new ReindexSourceItemsBySkus(
            $this->getSourceItemsBySku,
            $this->getSourceItemIds,
            $this->sourceItemIndexer
        );
    }

    public function testItReindexesEverySourceItemOfEverySku(): void
    {
        $first = $this->createMock(SourceItemInterface::class);
        $second = $this->createMock(SourceItemInterface::class);
        $third = $this->createMock(SourceItemInterface::class);

        $this->getSourceItemsBySku->method('execute')
            ->willReturnMap([
                ['sku-1', [$first, $second]],
                ['sku-2', [$third]],
            ]);
        $this->getSourceItemIds->expects(self::once())
            ->method('execute')
            ->with([$first, $second, $third])
            ->willReturn([10, 11, 12]);
        $this->sourceItemIndexer->expects(self::once())
            ->method('executeList')
            ->with([10, 11, 12]);

        $this->model->execute(['sku-1', 'sku-2']);
    }

    public function testItDoesNotReindexWhenTheSkusHaveNoSourceItems(): void
    {
        $this->getSourceItemsBySku->method('execute')->willReturn([]);
        $this->getSourceItemIds->expects(self::never())->method('execute');
        $this->sourceItemIndexer->expects(self::never())->method('executeList');

        $this->model->execute(['sku-1']);
    }

    public function testItDoesNotReindexWhenNoSourceItemIdResolves(): void
    {
        $this->getSourceItemsBySku->method('execute')
            ->willReturn([$this->createMock(SourceItemInterface::class)]);
        $this->getSourceItemIds->method('execute')->willReturn([]);
        $this->sourceItemIndexer->expects(self::never())->method('executeList');

        $this->model->execute(['sku-1']);
    }

    public function testItDoesNothingForAnEmptySkuList(): void
    {
        $this->getSourceItemsBySku->expects(self::never())->method('execute');
        $this->sourceItemIndexer->expects(self::never())->method('executeList');

        $this->model->execute([]);
    }
}
