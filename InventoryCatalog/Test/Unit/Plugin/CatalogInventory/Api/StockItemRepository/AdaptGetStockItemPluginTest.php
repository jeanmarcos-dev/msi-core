<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\CatalogInventory\Api\StockItemRepository;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\InventoryCatalog\Plugin\CatalogInventory\Api\StockItemRepository\AdaptGetStockItemPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdaptGetStockItemPluginTest extends TestCase
{
    private const STOCK_ITEM_ID = 43;
    private const SCOPE_ID = 0;

    /**
     * @var StockRegistryProviderInterface|MockObject
     */
    private $stockRegistryProvider;

    /**
     * @var StockItemRepositoryInterface|MockObject
     */
    private $subject;

    /**
     * @var AdaptGetStockItemPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->stockRegistryProvider = $this->createMock(StockRegistryProviderInterface::class);
        $this->subject = $this->createMock(StockItemRepositoryInterface::class);

        $stockConfiguration = $this->createMock(StockConfigurationInterface::class);
        $stockConfiguration->method('getDefaultScopeId')->willReturn(self::SCOPE_ID);

        $this->plugin = new AdaptGetStockItemPlugin($this->stockRegistryProvider, $stockConfiguration);
    }

    public function testItAnswersFromMsi(): void
    {
        $fromMsi = $this->createMock(StockItemInterface::class);
        $fromMsi->method('getItemId')->willReturn(self::STOCK_ITEM_ID);
        $this->stockRegistryProvider->expects($this->once())
            ->method('getStockItem')
            ->with(self::STOCK_ITEM_ID, self::SCOPE_ID)
            ->willReturn($fromMsi);

        $proceed = function () {
            self::fail('The frozen legacy table must not be consulted when MSI knows the product.');
        };

        self::assertSame($fromMsi, $this->plugin->aroundGet($this->subject, $proceed, self::STOCK_ITEM_ID));
    }

    /**
     * An id no product answers to is a not found error, and the original is the one that words it.
     */
    public function testItFallsBackWhenMsiDoesNotKnowTheId(): void
    {
        $empty = $this->createMock(StockItemInterface::class);
        $empty->method('getItemId')->willReturn(null);
        $this->stockRegistryProvider->method('getStockItem')->willReturn($empty);

        $fromLegacy = $this->createMock(StockItemInterface::class);
        $proceed = fn () => $fromLegacy;

        self::assertSame($fromLegacy, $this->plugin->aroundGet($this->subject, $proceed, self::STOCK_ITEM_ID));
    }
}
