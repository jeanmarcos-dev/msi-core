<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventoryConfigurationApi;

use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\SaveStockItemConfigurationInterface;
use Magento\InventoryIndexer\Model\ReindexSourceItemsBySkus;
use Magento\InventoryIndexer\Plugin\InventoryConfigurationApi\ReindexAfterStockItemConfigurationSavePlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexAfterStockItemConfigurationSavePluginTest extends TestCase
{
    /**
     * @var ReindexSourceItemsBySkus|MockObject
     */
    private $reindexSourceItemsBySkus;

    /**
     * @var ReindexAfterStockItemConfigurationSavePlugin
     */
    private $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reindexSourceItemsBySkus = $this->createMock(ReindexSourceItemsBySkus::class);
        $this->plugin = new ReindexAfterStockItemConfigurationSavePlugin($this->reindexSourceItemsBySkus);
    }

    public function testReindexesTheSkuWhoseConfigurationWasSaved(): void
    {
        $this->reindexSourceItemsBySkus
            ->expects(self::once())
            ->method('execute')
            ->with(['sku-1']);

        $this->plugin->afterExecute(
            $this->createMock(SaveStockItemConfigurationInterface::class),
            null,
            'sku-1',
            1,
            $this->createMock(StockItemConfigurationInterface::class)
        );
    }
}
