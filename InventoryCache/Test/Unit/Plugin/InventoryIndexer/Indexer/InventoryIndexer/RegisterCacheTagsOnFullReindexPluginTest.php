<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Test\Unit\Plugin\InventoryIndexer\Indexer\InventoryIndexer;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Indexer\CacheContext;
use Magento\InventoryCache\Plugin\InventoryIndexer\Indexer\InventoryIndexer\RegisterCacheTagsOnFullReindexPlugin;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RegisterCacheTagsOnFullReindexPluginTest extends TestCase
{
    /**
     * @var CacheContext|MockObject
     */
    private $cacheContext;

    /**
     * @var InventoryIndexer|MockObject
     */
    private $subject;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheContext = $this->createMock(CacheContext::class);
        $this->subject = $this->createMock(InventoryIndexer::class);
    }

    public function testRegistersTheConfiguredTagsAfterAFullReindex(): void
    {
        $this->cacheContext
            ->expects(self::once())
            ->method('registerTags')
            ->with([Product::CACHE_TAG, Category::CACHE_TAG]);

        $plugin = new RegisterCacheTagsOnFullReindexPlugin(
            $this->cacheContext,
            [Product::CACHE_TAG, Category::CACHE_TAG]
        );

        $plugin->beforeExecuteFull($this->subject);
    }

    public function testRegistersNothingWhenNoTagIsConfigured(): void
    {
        $this->cacheContext->expects(self::never())->method('registerTags');

        $plugin = new RegisterCacheTagsOnFullReindexPlugin($this->cacheContext, []);

        $plugin->beforeExecuteFull($this->subject);
    }
}
