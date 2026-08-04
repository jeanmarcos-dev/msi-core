<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Setup\Patch\Schema;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\Alias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;

/**
 * Replaces the Default Stock MySQL VIEW with an ordinary MSI index table.
 */
class ReplaceLegacyStockStatusViewWithIndexTable implements SchemaPatchInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexStructureInterface $indexStructure
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DefaultStockProviderInterface $defaultStockProvider,
        private readonly StockIndexTableNameResolverInterface $stockIndexTableNameResolver,
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly IndexStructureInterface $indexStructure,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $connection = $this->resourceConnection->getConnection();
        $stockId = $this->defaultStockProvider->getId();
        $tableName = $this->stockIndexTableNameResolver->execute($stockId);

        if ($this->isView($tableName)) {
            //phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
            $connection->query('DROP VIEW ' . $connection->quoteIdentifier($tableName));
        }

        $indexName = $this->indexNameBuilder
            ->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string)$stockId)
            ->setAlias(Alias::ALIAS_MAIN)
            ->build();
        if (!$this->indexStructure->isExist($indexName, ResourceConnection::DEFAULT_CONNECTION)) {
            $this->indexStructure->create($indexName, ResourceConnection::DEFAULT_CONNECTION);
        }

        $this->indexerRegistry->get(InventoryIndexer::INDEXER_ID)->invalidate();

        return $this;
    }

    /**
     * Whether the relation exists and is a VIEW rather than a base table.
     *
     * @param string $tableName
     * @return bool
     */
    private function isView(string $tableName): bool
    {
        $row = $this->resourceConnection->getConnection()->fetchRow('SHOW FULL TABLES LIKE ?', [$tableName]);

        return $row !== false && (string)array_values($row)[1] === 'VIEW';
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
        return [
            InitializeDefaultStock::class,
        ];
    }
}
