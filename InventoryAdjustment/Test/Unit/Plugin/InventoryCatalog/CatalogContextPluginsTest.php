<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\InventoryCatalog;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\CatalogInventory\Model\ResourceModel\QtyCounterInterface;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\LegacyQtyCounterContextPlugin;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\LegacyStockItemContextPlugin;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\MassUpdateContextPlugin;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\ProductCleanupContextPlugin;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\SkuRenameContextPlugin;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryCatalog\Model\DeleteSourceItemsBySkus;
use Magento\InventoryCatalog\Model\UpdateInventory;
use Magento\InventoryCatalog\Model\UpdateInventory\InventoryData;
use Magento\InventoryCatalog\Model\UpdateSourceItemBasedOnLegacyStockItem;
use Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product\CreateSourceItemsPlugin;
use Magento\InventoryCatalog\Plugin\CatalogInventory\UpdateSourceItemAtLegacyQtyCounterPlugin;
use PHPUnit\Framework\TestCase;

class CatalogContextPluginsTest extends TestCase
{
    /**
     * @var AdjustmentContext
     */
    private AdjustmentContext $context;

    /**
     * @var AdjustmentMetadataInterface|null
     */
    private ?AdjustmentMetadataInterface $seen = null;

    protected function setUp(): void
    {
        $this->context = new AdjustmentContext();
    }

    public function testLegacyStockItemSaveIsALegacyBridge(): void
    {
        $result = (new LegacyStockItemContextPlugin($this->context))->aroundExecute(
            $this->createMock(UpdateSourceItemBasedOnLegacyStockItem::class),
            fn () => $this->see(true),
            $this->createMock(Item::class)
        );

        self::assertTrue($result);
        $this->assertSeen('legacy_bridge', null);
    }

    public function testLegacyQtyCounterIsALegacyBridge(): void
    {
        $arguments = null;
        (new LegacyQtyCounterContextPlugin($this->context))->aroundAroundCorrectItemsQty(
            $this->createMock(UpdateSourceItemAtLegacyQtyCounterPlugin::class),
            function (...$given) use (&$arguments): void {
                $arguments = $given;
                $this->see(null);
            },
            $this->createMock(QtyCounterInterface::class),
            fn () => null,
            [7 => 2.0],
            1,
            '-'
        );

        self::assertSame([[7 => 2.0], 1, '-'], array_slice($arguments, 2));
        $this->assertSeen('legacy_bridge', null);
    }

    public function testMassUpdateIsACorrection(): void
    {
        (new MassUpdateContextPlugin($this->context))->aroundExecute(
            $this->createMock(UpdateInventory::class),
            fn () => $this->see(null),
            $this->createMock(InventoryData::class)
        );

        $this->assertSeen('correction', null);
    }

    public function testSkuRenameIsNoted(): void
    {
        $result = $this->createMock(Product::class);

        $returned = (new SkuRenameContextPlugin($this->context))->aroundAfterSave(
            $this->createMock(CreateSourceItemsPlugin::class),
            fn () => $this->see($result),
            $this->createMock(Product::class),
            $result,
            $this->createMock(AbstractModel::class)
        );

        self::assertSame($result, $returned);
        $this->assertSeen('other', 'sku_rename');
    }

    public function testProductCleanupIsNoted(): void
    {
        (new ProductCleanupContextPlugin($this->context))->aroundExecute(
            $this->createMock(DeleteSourceItemsBySkus::class),
            fn () => $this->see(null),
            ['SKU-1']
        );

        $this->assertSeen('other', 'product_deleted');
    }

    private function see(mixed $result): mixed
    {
        $this->seen = $this->context->getCurrent();
        return $result;
    }

    private function assertSeen(string $reason, ?string $note): void
    {
        self::assertNotNull($this->seen);
        self::assertSame($reason, $this->seen->getReason()->value);
        self::assertSame($note, $this->seen->getNote());
        self::assertNull($this->context->getCurrent());
    }
}
