<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemIds;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;
use Magento\InventoryIndexer\Model\ReindexSourceItemsBySkus;
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

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->getSourceItemsBySku = $this->createMock(GetSourceItemsBySkuInterface::class);
        $this->getSourceItemIds = $this->createMock(GetSourceItemIds::class);
        $this->sourceItemIndexer = $this->createMock(SourceItemIndexer::class);

        $this->model = new ReindexSourceItemsBySkus(
            $this->getSourceItemsBySku,
            $this->getSourceItemIds,
            $this->sourceItemIndexer
        );
    }

    public function testReindexesEverySourceItemOfEverySku(): void
    {
        $first = $this->createMock(SourceItemInterface::class);
        $second = $this->createMock(SourceItemInterface::class);

        $this->getSourceItemsBySku
            ->method('execute')
            ->willReturnMap([['sku-1', [$first]], ['sku-2', [$second]]]);
        $this->getSourceItemIds
            ->expects(self::once())
            ->method('execute')
            ->with([$first, $second])
            ->willReturn([11, 22]);
        $this->sourceItemIndexer
            ->expects(self::once())
            ->method('executeList')
            ->with([11, 22]);

        $this->model->execute(['sku-1', 'sku-2']);
    }

    public function testDoesNotReachTheIndexerWithoutSkus(): void
    {
        $this->getSourceItemsBySku->expects(self::never())->method('execute');
        $this->getSourceItemIds->expects(self::never())->method('execute');
        $this->sourceItemIndexer->expects(self::never())->method('executeList');

        $this->model->execute([]);
    }

    public function testDoesNotReachTheIndexerWhenTheSkusOwnNoSourceItem(): void
    {
        $this->getSourceItemsBySku->method('execute')->willReturn([]);
        $this->getSourceItemIds->method('execute')->willReturn([]);
        $this->sourceItemIndexer->expects(self::never())->method('executeList');

        $this->model->execute(['sku-1']);
    }
}
