<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventoryConfigurationApi;

use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\SaveStockItemConfigurationInterface;
use Magento\InventoryIndexer\Model\ReindexSourceItemsBySkus;

/**
 * Rebuild the stock index after the configuration that decides salability is written.
 *
 * The index resolves manage_stock, backorders and min_qty when it is built, not when it is read, so
 * a configuration saved on its own leaves a row that answers with the previous rules. Nothing else
 * reindexes here: the mview watches source items only, and the write path never touches one.
 */
class ReindexAfterStockItemConfigurationSavePlugin
{
    /**
     * @var ReindexSourceItemsBySkus
     */
    private $reindexSourceItemsBySkus;

    /**
     * @param ReindexSourceItemsBySkus $reindexSourceItemsBySkus
     */
    public function __construct(ReindexSourceItemsBySkus $reindexSourceItemsBySkus)
    {
        $this->reindexSourceItemsBySkus = $reindexSourceItemsBySkus;
    }

    /**
     * Rebuild the stock index of the sku whose configuration was just saved.
     *
     * @param SaveStockItemConfigurationInterface $subject
     * @param void $result
     * @param string $sku
     * @param int $stockId
     * @param StockItemConfigurationInterface $stockItemConfiguration
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SaveStockItemConfigurationInterface $subject,
        $result,
        string $sku,
        int $stockId,
        StockItemConfigurationInterface $stockItemConfiguration
    ) {
        $this->reindexSourceItemsBySkus->execute([$sku]);

        return $result;
    }
}
