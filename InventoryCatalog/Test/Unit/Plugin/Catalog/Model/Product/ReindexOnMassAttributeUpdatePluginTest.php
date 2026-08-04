<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\Catalog\Model\Product;

use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\InventoryCatalog\Plugin\Catalog\Model\Product\ReindexOnMassAttributeUpdatePlugin;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexOnMassAttributeUpdatePluginTest extends TestCase
{
    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $getSkusByProductIds;

    /**
     * @var ReindexSourceItemsBySkus|MockObject
     */
    private $reindexSourceItemsBySkus;

    /**
     * @var ProductAction|MockObject
     */
    private $productAction;

    /**
     * @var ReindexOnMassAttributeUpdatePlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $this->reindexSourceItemsBySkus = $this->createMock(ReindexSourceItemsBySkus::class);
        $this->productAction = $this->createMock(ProductAction::class);

        $this->plugin = new ReindexOnMassAttributeUpdatePlugin(
            $this->getSkusByProductIds,
            $this->reindexSourceItemsBySkus
        );
    }

    public function testItReindexesTheSkusOfTheUpdatedProducts(): void
    {
        $this->getSkusByProductIds->expects(self::once())
            ->method('execute')
            ->with([1, 2])
            ->willReturn([1 => 'sku-1', 2 => 'sku-2']);
        $this->reindexSourceItemsBySkus->expects(self::once())
            ->method('execute')
            ->with([1 => 'sku-1', 2 => 'sku-2']);

        self::assertSame(
            $this->productAction,
            $this->plugin->afterUpdateAttributes($this->productAction, $this->productAction, [1, 2])
        );
    }

    public function testItDeduplicatesTheProductIds(): void
    {
        $this->getSkusByProductIds->expects(self::once())
            ->method('execute')
            ->with([1])
            ->willReturn([1 => 'sku-1']);

        $this->plugin->afterUpdateAttributes($this->productAction, $this->productAction, ['1', 1]);
    }

    public function testItDoesNotReindexWhenNoProductWasUpdated(): void
    {
        $this->getSkusByProductIds->expects(self::never())->method('execute');
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $this->plugin->afterUpdateAttributes($this->productAction, $this->productAction, []);
    }
}
