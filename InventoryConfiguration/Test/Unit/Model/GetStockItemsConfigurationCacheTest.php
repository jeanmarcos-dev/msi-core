<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\InventoryConfiguration\Model\GetStockItemsConfiguration;
use Magento\InventoryConfiguration\Model\GetStockItemsConfigurationCache;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetStockItemsConfigurationCacheTest extends TestCase
{
    /**
     * @var GetStockItemsConfiguration|MockObject
     */
    private $getStockItemsConfigurationMock;

    /**
     * @var CacheStorage
     */
    private $cacheStorage;

    /**
     * @var GetStockItemsConfigurationCache
     */
    private $model;

    protected function setUp(): void
    {
        $this->getStockItemsConfigurationMock = $this->createMock(GetStockItemsConfiguration::class);
        $this->cacheStorage = new CacheStorage();
        $this->model = new GetStockItemsConfigurationCache(
            $this->getStockItemsConfigurationMock,
            $this->cacheStorage
        );
    }

    public function testOnlyUncachedSkusReachTheLoader(): void
    {
        $cached = $this->createMock(StockItemInterface::class);
        $loaded = $this->createMock(StockItemInterface::class);
        $this->cacheStorage->set('SKU-1', $cached);

        $this->getStockItemsConfigurationMock->expects(self::once())
            ->method('execute')
            ->with(['SKU-2'])
            ->willReturn(['SKU-2' => $loaded]);

        self::assertSame(
            ['SKU-1' => $cached, 'SKU-2' => $loaded],
            $this->model->execute(['SKU-1', 'SKU-2'])
        );
    }

    public function testLoadedItemsAreCachedForTheNextCall(): void
    {
        $loaded = $this->createMock(StockItemInterface::class);
        $this->getStockItemsConfigurationMock->expects(self::once())
            ->method('execute')
            ->with(['SKU-1'])
            ->willReturn(['SKU-1' => $loaded]);

        $this->model->execute(['SKU-1']);
        $this->model->execute(['SKU-1']);

        self::assertSame($loaded, $this->cacheStorage->get('SKU-1'));
    }

    public function testCleanEvictsTheGivenSkus(): void
    {
        $this->cacheStorage->set('SKU-1', $this->createMock(StockItemInterface::class));

        $this->model->clean(['SKU-1'], null);

        self::assertNull($this->cacheStorage->get('SKU-1'));
    }
}
