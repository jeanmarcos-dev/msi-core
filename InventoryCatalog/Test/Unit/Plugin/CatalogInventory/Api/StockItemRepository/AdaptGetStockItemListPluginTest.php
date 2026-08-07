<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\CatalogInventory\Api\StockItemRepository;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemCollectionInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\InventoryCatalog\Model\StockRegistryProvider;
use Magento\InventoryCatalog\Plugin\CatalogInventory\Api\StockItemRepository\AdaptGetStockItemListPlugin;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $collection->expects(self::once())->method('getItems');
        $collection->expects(self::once())->method('setItems')->with(array_values($msiItems));

        self::assertSame(
            $collection,
            $this->plugin->afterGetList($this->subject, $collection, $this->criteriaFor(self::PRODUCT_IDS))
        );
    }

    public function testACriteriaWithoutProductsIsLeftToTheOriginalMethod(): void
    {
        $this->stockRegistryProvider->expects(self::never())->method('getStockItems');

        $collection = $this->createMock(StockItemCollectionInterface::class);
        $collection->expects(self::never())->method('setItems');

        self::assertSame(
            $collection,
            $this->plugin->afterGetList($this->subject, $collection, $this->criteriaWithoutProducts())
        );
    }

    /**
     * @param mixed $productsFilter
     * @param int[] $expectedIds
     */
    #[DataProvider('productsFilterProvider')]
    public function testItNormalizesTheProductsFilterToIds(mixed $productsFilter, array $expectedIds): void
    {
        $this->assertNormalizesTo($productsFilter, $expectedIds);
    }

    public function testItReadsTheIdOfAProductEntity(): void
    {
        $this->assertNormalizesTo($this->product(22), [22]);
    }

    public function testItNormalizesAListMixingIdsAndProductEntities(): void
    {
        $this->assertNormalizesTo([11, $this->product(22), '33'], [11, 22, 33]);
    }

    /**
     * @param mixed $productsFilter
     */
    #[DataProvider('emptyProductsFilterProvider')]
    public function testAProductsFilterWithoutUsableIdsIsLeftToTheOriginalMethod(mixed $productsFilter): void
    {
        $this->stockRegistryProvider->expects(self::never())->method('getStockItems');

        $collection = $this->createMock(StockItemCollectionInterface::class);
        $collection->expects(self::never())->method('setItems');

        self::assertSame(
            $collection,
            $this->plugin->afterGetList($this->subject, $collection, $this->criteriaFor($productsFilter))
        );
    }

    public static function productsFilterProvider(): array
    {
        return [
            'a bare id, the form ChangeParentStockStatus passes' => [11, [11]],
            'a bare id as a numeric string' => ['11', [11]],
            'a list of ids' => [[11, 22], [11, 22]],
            'a list of ids as numeric strings' => [['11', '22'], [11, 22]],
            'a list with holes in it' => [[11, null, '', 22], [11, 22]],
        ];
    }

    public static function emptyProductsFilterProvider(): array
    {
        return [
            'a null product' => [null],
            'an empty list' => [[]],
            'an empty string' => [''],
            'a list of nothing but holes' => [[null, '']],
        ];
    }

    /**
     * @param mixed $productsFilter
     * @param int[] $expectedIds
     */
    private function assertNormalizesTo(mixed $productsFilter, array $expectedIds): void
    {
        $this->stockRegistryProvider->expects(self::once())
            ->method('getStockItems')
            ->with($expectedIds, self::SCOPE_ID)
            ->willReturn([]);

        $this->plugin->afterGetList(
            $this->subject,
            $this->createMock(StockItemCollectionInterface::class),
            $this->criteriaFor($productsFilter)
        );
    }

    /**
     * @param int $id
     * @return ProductInterface|MockObject
     */
    private function product(int $id)
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    /**
     * @param mixed $productsFilter
     * @return StockItemCriteriaInterface|MockObject
     */
    private function criteriaFor(mixed $productsFilter)
    {
        $criteria = $this->createMock(StockItemCriteriaInterface::class);
        $criteria->method('getPart')->willReturnCallback(
            fn (string $name) => match ($name) {
                'products_filter' => [$productsFilter],
                'website_filter' => [self::SCOPE_ID],
                default => null,
            }
        );

        return $criteria;
    }

    /**
     * @return StockItemCriteriaInterface|MockObject
     */
    private function criteriaWithoutProducts()
    {
        $criteria = $this->createMock(StockItemCriteriaInterface::class);
        $criteria->method('getPart')->willReturnCallback(
            fn (string $name) => $name === 'website_filter' ? [self::SCOPE_ID] : null
        );

        return $criteria;
    }
}
