<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product\ReindexCompositeProductOnSavePlugin;
use Magento\InventoryIndexer\Indexer\CompositeProductsIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexCompositeProductOnSavePluginTest extends TestCase
{
    private const SKU = 'configurable-1';

    /**
     * @var CompositeProductsIndexer|MockObject
     */
    private $compositeProductsIndexer;

    /**
     * @var ProductResource|MockObject
     */
    private $subject;

    /**
     * @var ProductResource|MockObject
     */
    private $result;

    /**
     * @var ReindexCompositeProductOnSavePlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->compositeProductsIndexer = $this->createMock(CompositeProductsIndexer::class);
        $this->subject = $this->createMock(ProductResource::class);
        $this->result = $this->createMock(ProductResource::class);
        $this->plugin = new ReindexCompositeProductOnSavePlugin($this->compositeProductsIndexer);
    }

    public function testItReindexesACompositeProduct(): void
    {
        // setIsChangedCategories() and setAffectedCategoryIds() are magic setters, so they can only be
        // observed through the data they write.
        $product = $this->createPartialMock(
            Product::class,
            ['isComposite', 'getSku', 'getCategoryIds', 'cleanModelCache']
        );
        $product->method('isComposite')->willReturn(true);
        $product->method('getSku')->willReturn(self::SKU);
        $product->method('getCategoryIds')->willReturn([3, 5]);

        $this->compositeProductsIndexer->expects($this->once())->method('reindexList')->with([self::SKU]);
        $product->expects($this->once())->method('cleanModelCache');

        $this->assertSame($this->result, $this->plugin->afterSave($this->subject, $this->result, $product));
        $this->assertTrue($product->getData('is_changed_categories'));
        $this->assertSame([3, 5], $product->getData('affected_category_ids'));
    }

    /**
     * The plugin runs on every product save, so the guard is what keeps a simple product save from
     * paying for a reindex it has no children to justify.
     */
    public function testItSkipsANonCompositeProduct(): void
    {
        $product = $this->createPartialMock(Product::class, ['isComposite', 'cleanModelCache']);
        $product->method('isComposite')->willReturn(false);

        $this->compositeProductsIndexer->expects($this->never())->method('reindexList');
        $product->expects($this->never())->method('cleanModelCache');

        $this->assertSame($this->result, $this->plugin->afterSave($this->subject, $this->result, $product));
    }

    /**
     * The resource model saves entities other than products, and only a product knows about categories.
     */
    public function testItSkipsAnEntityThatIsNotAProduct(): void
    {
        $this->compositeProductsIndexer->expects($this->never())->method('reindexList');

        $entity = $this->createMock(AbstractModel::class);

        $this->assertSame($this->result, $this->plugin->afterSave($this->subject, $this->result, $entity));
    }
}
