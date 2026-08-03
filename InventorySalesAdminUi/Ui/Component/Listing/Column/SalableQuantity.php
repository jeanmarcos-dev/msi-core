<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\InventorySalesAdminUi\Model\AddSourceSalableQuantityBreakdown;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetAssignedStockIdsBySku;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Add grid column with salable quantity data
 */
class SalableQuantity extends Column
{
    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    private $isSourceItemManagementAllowedForProductType;

    /**
     * @var GetSalableQuantityDataBySku
     */
    private $getSalableQuantityDataBySku;

    /**
     * @var GetAssignedStockIdsBySku
     */
    private $getAssignedStockIdsBySku;

    /**
     * @var AddSourceSalableQuantityBreakdown
     */
    private $addSourceSalableQuantityBreakdown;

    /**
     * @var int
     */
    private $maximumStocksToShow;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     * @param GetSalableQuantityDataBySku $getSalableQuantityDataBySku
     * @param GetAssignedStockIdsBySku $getAssignedStockIdsBySku
     * @param AddSourceSalableQuantityBreakdown $addSourceSalableQuantityBreakdown
     * @param int $maximumStocksToShow
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        GetAssignedStockIdsBySku $getAssignedStockIdsBySku,
        AddSourceSalableQuantityBreakdown $addSourceSalableQuantityBreakdown,
        int $maximumStocksToShow,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->getAssignedStockIdsBySku = $getAssignedStockIdsBySku;
        $this->addSourceSalableQuantityBreakdown = $addSourceSalableQuantityBreakdown;
        $this->maximumStocksToShow = $maximumStocksToShow;
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if ($dataSource['data']['totalRecords'] > 0) {
            foreach ($dataSource['data']['items'] as &$row) {
                $row['salable_quantity'] =
                    $this->isSourceItemManagementAllowedForProductType->execute($row['type_id']) === true
                    ? $this->getSalableQuantityItemData($row['sku'])
                    : [];
            }
            unset($row);

            $dataSource['data']['items'] = $this->addSourceBreakdown($dataSource['data']['items']);
        }

        return $dataSource;
    }

    /**
     * Add the per-source breakdown to the stock entries of every grid row.
     *
     * The whole page goes through one call, so the breakdown costs a full page the same number of
     * queries it costs a single row.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function addSourceBreakdown(array $items): array
    {
        $stockEntriesBySku = [];
        foreach ($items as $item) {
            $stockEntriesBySku[$this->decodeSku($item['sku'])] = $item['salable_quantity'];
        }

        $stockEntriesBySku = $this->addSourceSalableQuantityBreakdown->execute($stockEntriesBySku);

        foreach ($items as $key => $item) {
            $items[$key]['salable_quantity'] = $stockEntriesBySku[$this->decodeSku($item['sku'])];
        }

        return $items;
    }

    /**
     * Decode a SKU coming from the grid data source.
     *
     * @param string $sku
     * @return string
     */
    private function decodeSku(string $sku): string
    {
        return htmlspecialchars_decode($sku, ENT_QUOTES | ENT_SUBSTITUTE);
    }

    /**
     * Get salable quantity data for product
     *
     * @param string $sku
     * @return array
     */
    private function getSalableQuantityItemData(string $sku): array
    {
        $sku = $this->decodeSku($sku);

        $stockIds = $this->getAssignedStockIdsBySku->execute($sku);
        if (count($stockIds) > $this->maximumStocksToShow) {
            return [
                [
                    'manage_stock' => true,
                    'message' => __('Associated to %1 stocks', count($stockIds)),
                ]
            ];
        }

        $salableQuantityData = $this->getSalableQuantityDataBySku->execute($sku);

        return $salableQuantityData;
    }
}
