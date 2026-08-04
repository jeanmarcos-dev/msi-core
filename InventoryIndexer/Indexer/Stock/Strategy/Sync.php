<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\InventoryIndexer\Indexer\Stock\Strategy;

use Magento\Framework\App\ResourceConnection;
use Magento\Indexer\Model\ProcessManager;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStockFactory;
use Magento\InventoryIndexer\Indexer\Stock\GetAllStockIds;
use Magento\InventoryIndexer\Indexer\Stock\IndexDataFiller;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexTableSwitcherInterface;

/**
 * Reindex stocks synchronously.
 */
class Sync
{
    /**
     * @var string
     */
    private string $connectionName = ResourceConnection::DEFAULT_CONNECTION;

    /**
     * @param GetAllStockIds $getAllStockIds
     * @param IndexStructureInterface $indexStructure
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexTableSwitcherInterface $indexTableSwitcher
     * @param SkuListInStockFactory $skuListInStockFactory
     * @param IndexDataFiller $indexDataFiller
     * @param ProcessManager $processManager
     */
    public function __construct(
        private readonly GetAllStockIds $getAllStockIds,
        private readonly IndexStructureInterface $indexStructure,
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly IndexTableSwitcherInterface $indexTableSwitcher,
        private readonly SkuListInStockFactory $skuListInStockFactory,
        private readonly IndexDataFiller $indexDataFiller,
        private readonly ProcessManager $processManager,
    ) {
    }

    /**
     * Reindex all stocks.
     *
     * @return void
     */
    public function executeFull(): void
    {
        $stockIds = $this->getAllStockIds->execute();
        $this->executeList($stockIds);
    }

    /**
     * Reindex single stock by id.
     *
     * @param int $stockId
     * @return void
     */
    public function executeRow(int $stockId): void
    {
        $this->executeList([$stockId]);
    }

    /**
     * Reindex list of stock by provided ids.
     *
     * @param int[] $stockIds
     * @return void
     */
    public function executeList(array $stockIds): void
    {
        $stocksToReindex = array_map('intval', $stockIds);

        if (count($stocksToReindex) > 1) {
            $userFunctions = [];
            foreach ($stocksToReindex as $stockId) {
                $userFunctions[] = fn () => $this->reindexStock($stockId);
            }
            $this->processManager->execute($userFunctions);
        } elseif ($stocksToReindex) {
            $this->reindexStock($stocksToReindex[0]);
        }
    }

    /**
     * Rebuild the index of a single stock through the replica table.
     *
     * @param int $stockId
     * @return void
     */
    private function reindexStock(int $stockId): void
    {
        $replicaIndexName = $this->indexNameBuilder->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string) $stockId)
            ->setAlias(IndexAlias::REPLICA->value)
            ->build();
        $this->indexStructure->delete($replicaIndexName, $this->connectionName);
        $this->indexStructure->create($replicaIndexName, $this->connectionName);

        $skuListInStock = $this->skuListInStockFactory->create(['stockId' => $stockId]);
        $this->indexDataFiller->fillIndex($replicaIndexName, $skuListInStock, $this->connectionName);

        $mainIndexName = $this->indexNameBuilder->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string) $stockId)
            ->setAlias(IndexAlias::MAIN->value)
            ->build();
        if (!$this->indexStructure->isExist($mainIndexName, $this->connectionName)) {
            $this->indexStructure->create($mainIndexName, $this->connectionName);
        }
        $this->indexTableSwitcher->switch($mainIndexName, $this->connectionName);
        $this->indexStructure->delete($replicaIndexName, $this->connectionName);
    }
}
