<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventoryApi;

use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemIds;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;
use Magento\InventoryIndexer\Plugin\InventoryApi\ReindexAfterSourceItemsSavePlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexAfterSourceItemsSavePluginTest extends TestCase
{
    /**
     * @var GetSourceItemIds|MockObject
     */
    private $getSourceItemIds;

    /**
     * @var SourceItemIndexer|MockObject
     */
    private $sourceItemIndexer;

    /**
     * @var SourceItemInterface|MockObject
     */
    private $sourceItem;

    /**
     * @var SourceItemsSaveInterface|MockObject
     */
    private $subject;

    /**
     * @var ReindexAfterSourceItemsSavePlugin
     */
    private $plugin;

    /**
     * @inheridoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->getSourceItemIds = $this->createMock(GetSourceItemIds::class);
        $this->sourceItemIndexer = $this->createMock(SourceItemIndexer::class);
        $this->sourceItem = $this->createMock(SourceItemInterface::class);
        $this->subject = $this->createMock(SourceItemsSaveInterface::class);
        $this->plugin = new ReindexAfterSourceItemsSavePlugin(
            $this->getSourceItemIds,
            $this->sourceItemIndexer
        );
    }

    public function testAfterExecuteWithDefaultSource() : void
    {
        $this->getSourceItemIds->expects($this->once())
            ->method('execute')
            ->with([$this->sourceItem])
            ->willReturn([7]);
        $this->sourceItemIndexer->expects($this->once())
            ->method('executeList')
            ->with([7]);
        $this->plugin->afterExecute($this->subject, null, [$this->sourceItem]);
    }
}
