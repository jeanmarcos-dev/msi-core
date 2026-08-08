<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Plugin\InventoryIndexer\Indexer\InventoryIndexer;

use Magento\Framework\Indexer\CacheContext;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;

/**
 * Register the catalog cache tags a full stock reindex invalidates.
 *
 * A full reindex rewrites every stock row at once, so the per-product diff the incremental path
 * relies on has nothing to compare against. The retired legacy stock indexer used to register
 * these tags for the Default Stock; what covers them today is the price reindex the salability
 * pool triggers, and that one stops registering anything as soon as the price indexer is set to
 * update by schedule. The framework flushes whatever an indexer registers while it runs.
 */
class RegisterCacheTagsOnFullReindexPlugin
{
    /**
     * @var CacheContext
     */
    private $cacheContext;

    /**
     * @var string[]
     */
    private $cacheTags;

    /**
     * @param CacheContext $cacheContext
     * @param string[] $cacheTags
     */
    public function __construct(CacheContext $cacheContext, array $cacheTags = [])
    {
        $this->cacheContext = $cacheContext;
        $this->cacheTags = $cacheTags;
    }

    /**
     * Register the catalog cache tags the full reindex about to run invalidates.
     *
     * @param InventoryIndexer $subject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeExecuteFull(InventoryIndexer $subject)
    {
        if ($this->cacheTags) {
            $this->cacheContext->registerTags($this->cacheTags);
        }
    }
}
