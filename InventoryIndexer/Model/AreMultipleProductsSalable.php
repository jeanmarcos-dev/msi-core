<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterfaceFactory;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Psr\Log\LoggerInterface;

/**
 * Determine the salability of multiple products in a specified stock.
 */
class AreMultipleProductsSalable implements AreProductsSalableInterface
{
    /**
     * @param GetStockItemsDataInterface $getStockItemsData
     * @param IsProductSalableResultInterfaceFactory $isProductSalableResultFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GetStockItemsDataInterface $getStockItemsData,
        private readonly IsProductSalableResultInterfaceFactory $isProductSalableResultFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        $isSalableResults = [];
        try {
            $stockItemsData = $this->getStockItemsData->execute($skus, $stockId);

            foreach ($stockItemsData as $sku => $stockItemData) {
                $isSalable = (bool)($stockItemData[GetStockItemsDataInterface::IS_SALABLE] ?? false);
                $isSalableResults[$sku] = $isSalable;
            }
        } catch (LocalizedException $exception) {
            $this->logger->warning(
                sprintf(
                    'Unable to fetch stock #%s data for SKUs %s. Reason: %s',
                    $stockId,
                    implode(', ', $skus),
                    $exception->getMessage()
                )
            );
        }

        // Driven by the requested SKUs rather than by the rows the index returned: a SKU with no
        // index row is not salable, and dropping it would hand callers a result set shorter than
        // the list they asked about. Callers index into it positionally.
        $results = [];
        foreach ($skus as $sku) {
            $results[] = $this->isProductSalableResultFactory->create(
                [
                    'sku' => $sku,
                    'stockId' => $stockId,
                    'isSalable' => $isSalableResults[$sku] ?? false,
                ]
            );
        }

        return $results;
    }
}
