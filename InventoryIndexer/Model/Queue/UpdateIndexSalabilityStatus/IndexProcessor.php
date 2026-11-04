<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\StateException;
use Magento\InventoryCatalogApi\Model\GetParentSkusOfChildrenSkusInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\DataProvider;
use Magento\InventoryIndexer\Model\Queue\GetSalabilityDataForUpdate;
use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\ResourceModel\UpdateIsSalable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\Alias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;

/**
 * Update 'is salable' index data processor.
 */
class IndexProcessor
{
    /**
     * @var string
     */
    private string $connectionName = ResourceConnection::DEFAULT_CONNECTION;

    /**
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexStructureInterface $indexStructure
     * @param UpdateIsSalable $updateIsSalable
     * @param GetSalabilityDataForUpdate $getSalabilityDataForUpdate
     * @param GetParentSkusOfChildrenSkusInterface $getParentSkusOfChildrenSkus
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param GetStockItemsDataInterface $getStockItemsData
     * @param DataProvider $dataProvider
     * @param string[] $parentTypesDerivedFromChildren
     */
    public function __construct(
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly IndexStructureInterface $indexStructure,
        private readonly UpdateIsSalable $updateIsSalable,
        private readonly GetSalabilityDataForUpdate $getSalabilityDataForUpdate,
        private readonly GetParentSkusOfChildrenSkusInterface $getParentSkusOfChildrenSkus,
        private readonly GetProductTypesBySkusInterface $getProductTypesBySkus,
        private readonly GetStockItemsDataInterface $getStockItemsData,
        private readonly DataProvider $dataProvider,
        private readonly array $parentTypesDerivedFromChildren = [],
    ) {
    }

    /**
     * Process index for given reservation data and stock.
     *
     * @param ReservationData $reservationData
     * @return array<string, bool>
     * @throws StateException
     */
    public function execute(ReservationData $reservationData): array
    {
        $dataForUpdate = $this->withoutParentsDerivedFromChildren(
            $this->getSalabilityDataForUpdate->execute($reservationData)
        );

        $stockId = $reservationData->getStock();
        $mainIndexName = $this->indexNameBuilder->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string) $stockId)
            ->setAlias(IndexAlias::MAIN->value)
            ->build();
        if (!$this->indexStructure->isExist($mainIndexName, $this->connectionName)) {
            $this->indexStructure->create($mainIndexName, $this->connectionName);
        }
        $this->updateIsSalable->execute($mainIndexName, $dataForUpdate, $this->connectionName);

        $parentsForUpdate = $this->getParentsForUpdate(array_map('strval', array_keys($dataForUpdate)), $stockId);
        $this->updateIsSalable->execute($mainIndexName, $parentsForUpdate, $this->connectionName);

        return $dataForUpdate + $parentsForUpdate;
    }

    /**
     * Drop the parents whose salability is derived from their children's index rows.
     *
     * @param array<string,bool> $dataForUpdate
     * @return array<string,bool>
     */
    private function withoutParentsDerivedFromChildren(array $dataForUpdate): array
    {
        if (!$dataForUpdate || !$this->parentTypesDerivedFromChildren) {
            return $dataForUpdate;
        }

        $types = $this->getProductTypesBySkus->execute(array_map('strval', array_keys($dataForUpdate)));

        return array_filter(
            $dataForUpdate,
            fn ($sku): bool => !in_array($types[$sku] ?? null, $this->parentTypesDerivedFromChildren, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Recompute the derived parents of the given children from their index rows.
     *
     * @param string[] $childSkus
     * @param int $stockId
     * @return array<string,bool>
     */
    private function getParentsForUpdate(array $childSkus, int $stockId): array
    {
        if (!$childSkus || !$this->parentTypesDerivedFromChildren) {
            return [];
        }

        $parentSkus = array_merge([], ...array_values($this->getParentSkusOfChildrenSkus->execute($childSkus)));
        $parentSkus = array_values(array_unique(array_map('strval', $parentSkus)));
        if (!$parentSkus) {
            return [];
        }

        $parentSkusByType = [];
        foreach ($this->getProductTypesBySkus->execute($parentSkus) as $sku => $type) {
            if (in_array($type, $this->parentTypesDerivedFromChildren, true)) {
                $parentSkusByType[$type][] = (string)$sku;
            }
        }
        if (!$parentSkusByType) {
            return [];
        }

        $stored = $this->getStockItemsData->execute(array_merge([], ...array_values($parentSkusByType)), $stockId);
        $parentsForUpdate = [];
        foreach ($parentSkusByType as $type => $skus) {
            $computed = $this->dataProvider->getSiblingsData($stockId, $type, $skus);
            foreach ($skus as $sku) {
                $isSalable = (bool)($computed[$sku][IndexStructure::IS_SALABLE] ?? false);
                if ($isSalable !== (bool)($stored[$sku][GetStockItemsDataInterface::IS_SALABLE] ?? false)) {
                    $parentsForUpdate[$sku] = $isSalable;
                }
            }
        }

        return $parentsForUpdate;
    }
}
