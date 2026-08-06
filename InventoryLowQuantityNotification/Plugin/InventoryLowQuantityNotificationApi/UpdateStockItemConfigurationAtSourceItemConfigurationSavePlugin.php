<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotification\Plugin\InventoryLowQuantityNotificationApi;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\InventoryLowQuantityNotificationApi\Api\Data\SourceItemConfigurationInterface;
use Magento\InventoryLowQuantityNotificationApi\Api\SourceItemConfigurationsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryConfiguration\Model\UpdateStockItemConfiguration;

class UpdateStockItemConfigurationAtSourceItemConfigurationSavePlugin
{
    /**
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param UpdateStockItemConfiguration $updateStockItemConfiguration
     */
    public function __construct(
        private readonly IsSingleSourceModeInterface $isSingleSourceMode,
        private readonly DefaultSourceProviderInterface $defaultSourceProvider,
        private readonly UpdateStockItemConfiguration $updateStockItemConfiguration
    ) {
    }

    /**
     * Mirror the default source notification threshold into the stock item configuration.
     *
     * @param SourceItemConfigurationsSaveInterface $subject
     * @param void $result
     * @param SourceItemConfigurationInterface[] $sourceItemConfigurations
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SourceItemConfigurationsSaveInterface $subject,
        $result,
        array $sourceItemConfigurations
    ): void {
        if ($this->isSingleSourceMode->execute()) {
            return;
        }

        foreach ($sourceItemConfigurations as $sourceItemConfiguration) {
            if ($sourceItemConfiguration->getSourceCode() !== $this->defaultSourceProvider->getCode()) {
                continue;
            }

            $notifyStockQty = $sourceItemConfiguration->getNotifyStockQty();
            $this->updateStockItemConfiguration->execute(
                [(string)$sourceItemConfiguration->getSku()],
                [
                    StockItemInterface::NOTIFY_STOCK_QTY => $notifyStockQty ?? 1,
                    StockItemInterface::USE_CONFIG_NOTIFY_STOCK_QTY => $notifyStockQty ? 0 : 1,
                ]
            );
        }
    }
}
