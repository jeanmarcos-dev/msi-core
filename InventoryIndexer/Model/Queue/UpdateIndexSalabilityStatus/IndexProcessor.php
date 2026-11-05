<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\StateException;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Model\Queue\GetSalabilityDataForUpdate;
use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\ResourceModel\UpdateIsSalable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\Alias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;

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
     * @param DerivedParentSalability $derivedParentSalability
     */
    public function __construct(
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly IndexStructureInterface $indexStructure,
        private readonly UpdateIsSalable $updateIsSalable,
        private readonly GetSalabilityDataForUpdate $getSalabilityDataForUpdate,
        private readonly DerivedParentSalability $derivedParentSalability,
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
        $dataForUpdate = $this->derivedParentSalability->withoutDerivedParents(
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

        $parentsForUpdate = $this->derivedParentSalability->getChanges(
            array_map('strval', array_keys($dataForUpdate)),
            $stockId
        );
        $this->updateIsSalable->execute($mainIndexName, $parentsForUpdate, $this->connectionName);

        return $dataForUpdate + $parentsForUpdate;
    }
}
