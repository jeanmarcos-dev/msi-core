<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;

class ReindexAfterStatusImportObserver implements ObserverInterface
{
    private const STATUS_COLUMN = 'status';

    /**
     * @param ReindexSourceItemsBySkus $reindexSourceItemsBySkus
     */
    public function __construct(
        private readonly ReindexSourceItemsBySkus $reindexSourceItemsBySkus
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $skus = [];
        foreach ($observer->getEvent()->getData('bunch') as $row) {
            $sku = (string) ($row['sku'] ?? '');
            if ($sku !== '' && (string) ($row[self::STATUS_COLUMN] ?? '') !== '') {
                $skus[$sku] = $sku;
            }
        }

        if ($skus) {
            $this->reindexSourceItemsBySkus->execute(array_values($skus));
        }
    }
}
