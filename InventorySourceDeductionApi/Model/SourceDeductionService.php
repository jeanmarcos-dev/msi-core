<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySourceDeductionApi\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Model\InventoryConfigurationInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;

/**
 * @inheritdoc
 */
class SourceDeductionService implements SourceDeductionServiceInterface
{
    /**
     * @var GetSourceItemBySourceCodeAndSku
     */
    private $getSourceItemBySourceCodeAndSku;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var DecrementSourceItemQty
     */
    private $decrementSourceItem;

    /**
     * @var InventoryConfigurationInterface
     */
    private $inventoryConfiguration;

    /**
     * @param GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param DecrementSourceItemQty $decrementSourceItem
     * @param InventoryConfigurationInterface $inventoryConfiguration
     */
    public function __construct(
        GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        DecrementSourceItemQty $decrementSourceItem,
        InventoryConfigurationInterface $inventoryConfiguration
    ) {
        $this->getSourceItemBySourceCodeAndSku = $getSourceItemBySourceCodeAndSku;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->decrementSourceItem = $decrementSourceItem;
        $this->inventoryConfiguration = $inventoryConfiguration;
    }

    /**
     * @inheritdoc
     */
    public function execute(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $sourceCode = $sourceDeductionRequest->getSourceCode();
        $salesChannel = $sourceDeductionRequest->getSalesChannel();
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $sourceItemDecrementData = [];
        foreach ($sourceDeductionRequest->getItems() as $item) {
            $itemSku = $item->getSku();
            $qty = $item->getQty();
            $stockItemConfiguration = $this->getStockItemConfiguration->execute(
                $itemSku,
                $stockId
            );

            if (!$stockItemConfiguration->isManageStock()) {
                //We don't need to Manage Stock
                continue;
            }

            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($sourceCode, $itemSku);
            $quantityAfter = $sourceItem->getQuantity() - $qty;
            if ($quantityAfter < 0) {
                throw new LocalizedException(
                    __('Not all of your products are available in the requested quantity.')
                );
            }
            $status = $this->getStatusChange($stockItemConfiguration, $sourceItem, $qty, $quantityAfter);
            $sourceItem->setQuantity($quantityAfter);
            $decrementData = [
                'source_item' => $sourceItem,
                'qty_to_decrement' => $qty
            ];
            if ($status !== null) {
                $sourceItem->setStatus($status);
                $decrementData['status'] = $status;
            }
            $sourceItemDecrementData[] = $decrementData;
        }

        if (!empty($sourceItemDecrementData)) {
            $this->decrementSourceItem->execute($sourceItemDecrementData);
        }
    }

    /**
     * Get the status a deduction or a return moves the source item to, or null when it keeps its own.
     *
     * @param StockItemConfigurationInterface $stockItemConfiguration
     * @param SourceItemInterface $sourceItem
     * @param float $qty
     * @param float $quantityAfter
     * @return int|null
     */
    private function getStatusChange(
        StockItemConfigurationInterface $stockItemConfiguration,
        SourceItemInterface $sourceItem,
        float $qty,
        float $quantityAfter
    ): ?int {
        $minQty = (float)$stockItemConfiguration->getMinQty();
        if ($qty > 0 && $quantityAfter <= $minQty && !$stockItemConfiguration->getBackorders()) {
            return SourceItemInterface::STATUS_OUT_OF_STOCK;
        }
        if ($qty < 0 && $quantityAfter > $minQty
            && (int)$sourceItem->getStatus() === SourceItemInterface::STATUS_OUT_OF_STOCK
            && $this->inventoryConfiguration->isCanBackInStock()
        ) {
            return SourceItemInterface::STATUS_IN_STOCK;
        }

        return null;
    }
}
