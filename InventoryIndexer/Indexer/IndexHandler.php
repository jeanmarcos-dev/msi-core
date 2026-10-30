<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\InventoryApi\Model\CacheInterface;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemsDataCache;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexHandlerInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;

/**
 * Index handler is responsible for index data manipulation
 */
class IndexHandler implements IndexHandlerInterface
{
    /**
     * @var IndexNameResolverInterface
     */
    private $indexNameResolver;

    /**
     * @var Batch
     */
    private $batch;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var DeploymentConfig|null
     */
    private $deploymentConfig;

    /**
     * @var CacheInterface
     */
    private $stockItemDataCache;

    /**
     * Deployment config path
     *
     * @var string
     */
    private const DEPLOYMENT_CONFIG_INDEXER_BATCHES = 'indexer/batch_size/';

    /**
     * @param IndexNameResolverInterface $indexNameResolver
     * @param Batch $batch
     * @param ResourceConnection $resourceConnection
     * @param int $batchSize
     * @param DeploymentConfig|null $deploymentConfig
     * @param CacheInterface|null $stockItemDataCache
     */
    public function __construct(
        IndexNameResolverInterface $indexNameResolver,
        Batch $batch,
        ResourceConnection $resourceConnection,
        $batchSize,
        ?DeploymentConfig $deploymentConfig = null,
        ?CacheInterface $stockItemDataCache = null
    ) {
        $this->indexNameResolver = $indexNameResolver;
        $this->batch = $batch;
        $this->resourceConnection = $resourceConnection;
        $this->batchSize = $batchSize;
        $this->deploymentConfig = $deploymentConfig ?: ObjectManager::getInstance()->get(DeploymentConfig::class);
        $this->stockItemDataCache = $stockItemDataCache
            ?: ObjectManager::getInstance()->get(GetStockItemsDataCache::class);
    }

    /**
     * @inheritdoc
     */
    public function saveIndex(IndexName $indexName, \Traversable $documents, string $connectionName): void
    {
        $connection = $this->resourceConnection->getConnection($connectionName);
        $tableName = $this->indexNameResolver->resolveName($indexName);

        $columns = [IndexStructure::SKU, IndexStructure::QUANTITY, IndexStructure::IS_SALABLE];

        $this->batchSize = $this->deploymentConfig->get(
            self::DEPLOYMENT_CONFIG_INDEXER_BATCHES . InventoryIndexer::INDEXER_ID . '/' . 'default'
        ) ?? $this->batchSize;

        foreach ($this->batch->getItems($documents, $this->batchSize) as $batchDocuments) {
            $connection->insertOnDuplicate($tableName, $batchDocuments, $columns);
            $this->stockItemDataCache->clean(array_column($batchDocuments, IndexStructure::SKU), null);
        }
    }

    /**
     * @inheritdoc
     */
    public function cleanIndex(IndexName $indexName, \Traversable $documents, string $connectionName): void
    {
        $connection = $this->resourceConnection->getConnection($connectionName);
        $tableName = $this->indexNameResolver->resolveName($indexName);
        $skus = iterator_to_array($documents);
        $connection->delete($tableName, ['sku IN (?)' => $skus]);
        $this->stockItemDataCache->clean($skus, null);
    }
}
