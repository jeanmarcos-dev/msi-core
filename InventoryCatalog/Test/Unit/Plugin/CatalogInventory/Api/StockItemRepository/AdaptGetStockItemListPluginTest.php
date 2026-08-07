<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\CatalogInventory\Api\StockItemRepository;

use Magento\CatalogInventory\Api\Data\StockItemCollectionInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\InventoryCatalog\Model\StockRegistryProvider;
use Magento\InventoryCatalog\Plugin\CatalogInventory\Api\StockItemRepository\AdaptGetStockItemListPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdaptGetStockItemListPluginTest extends TestCase
{
    private const PRODUCT_IDS = [11, 22];
    private const SCOPE_ID = 1;

    /**
     * @var StockRegistryProvider|MockObject
     */
    private $stockRegistryProvider;

    /**
     * @var StockItemRepositoryInterface|MockObject
     */
    private $subject;

    /**
     * @var AdaptGetStockItemListPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->stockRegistryProvider = $this->createMock(StockRegistryProvider::class);
        $this->subject = $this->createMock(StockItemRepositoryInterface::class);
        $this->plugin = new AdaptGetStockItemListPlugin($this->stockRegistryProvider);
    }

    public function testItReplacesTheCollectionItemsWithTheOnesMsiHolds(): void
    {
        $msiItems = [
            11 => $this->createMock(StockItemInterface::class),
            22 => $this->createMock(StockItemInterface::class),
        ];
        $this->stockRegistryProvider->expects(self::once())
            ->method('getStockItems')
            ->with(self::PRODUCT_IDS, self::SCOPE_ID)
            ->willReturn($msiItems);

        $collection = $this->createMock(StockItemCollectionInterface::class);
        // Loading first is what keeps the lazy fetch from overwriting what the plugin sets.
        $collection->expects(self::once())->method('getItems');
        $collection->expects(self::once())->method('setItems')->with(array_values($msiItems));

        self::assertSame(
            $collection,
            $this->plugin->afterGetList($this->subject, $collection, $this->criteria(self::PRODUCT_IDS))
        );
    }

    public function testACriteriaWithoutProductsIsLeftToTheOriginalMethod(): void
    {
        $this->stockRegistryProvider->expects(self::never())->method('getStockItems');

        $collection = $this->createMock(StockItemCollectionInterface::class);
        $collection->expects(self::never())->method('setItems');

        self::assertSame(
            $collection,
            $this->plugin->afterGetList($this->subject, $collection, $this->criteria(null))
        );
    }

    /**
     * @param array|null $productIds
     * @return StockItemCriteriaInterface|MockObject
     */
    private function criteria(?array $productIds)
    {
        $criteria = $this->createMock(StockItemCriteriaInterface::class);
        $criteria->method('getPart')->willReturnCallback(
            fn (string $name) => match ($name) {
                'products_filter' => $productIds === null ? null : [$productIds],
                'website_filter' => [self::SCOPE_ID],
                default => null,
            }
        );

        return $criteria;
    }
}
