<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Setup\Patch\Schema;

use Magento\CatalogInventory\Model\Indexer\Stock\Processor as LegacyStockIndexer;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

/**
 * Takes the legacy stock indexer out of schedule mode.
 *
 * The indexer action is inert, so leaving it scheduled would only keep its mview triggers writing changelog
 * rows on every product save for an index nobody reads. Switching to realtime is what drops those triggers:
 * the subscriptions have to be removed while the view still knows about them, which rules out filtering the
 * mview configuration instead.
 */
class DisableLegacyStockIndexer implements SchemaPatchInterface
{
    /**
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(private readonly IndexerRegistry $indexerRegistry)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $indexer = $this->indexerRegistry->get(LegacyStockIndexer::INDEXER_ID);
        if ($indexer->isScheduled()) {
            $indexer->setScheduled(false);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }
}
