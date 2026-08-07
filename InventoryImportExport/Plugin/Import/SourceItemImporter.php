<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Plugin\Import;

use Magento\CatalogImportExport\Model\Import\Product\SkuStorage;
use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryIndexer\Indexer\CompositeProductsIndexer;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;

/**
 * Assigning products to default source
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SourceItemImporter
{
    /**
     * These inventory configurations affects all sources
     *
     * @var string[]
     */
    private const STOCK_CONFIGURATION_FIELDS = [
        'min_qty' => null,
        'use_config_min_qty' => null,
        'backorders' => null,
        'use_config_backorders' => null,
        'out_of_stock_qty' => null, // alias for min_qty
        'allow_backorders' => null // alias for backorders
    ];

    private const STOCK_ROW_FIELDS = self::STOCK_CONFIGURATION_FIELDS + [
        'qty' => null,
        'is_in_stock' => null,
    ];

    /**
     * StockItemImporter constructor
     *
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param SkuStorage $skuStorage
     * @param SourceItemResourceModel $sourceItemResourceModel
     * @param SourceItemIndexer $sourceItemIndexer
     * @param CompositeProductsIndexer $compositeProductsIndexer
     */
    public function __construct(
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly DefaultSourceProviderInterface $defaultSourceProvider,
        private readonly IsSingleSourceModeInterface $isSingleSourceMode,
        private readonly SkuStorage $skuStorage,
        private readonly SourceItemResourceModel $sourceItemResourceModel,
        private readonly SourceItemIndexer $sourceItemIndexer,
        private readonly CompositeProductsIndexer $compositeProductsIndexer,
    ) {
    }

    /**
     * After plugin Import to import Stock Data to Source Items
     *
     * @param StockItemProcessorInterface $subject
     * @param mixed $result
     * @param array $stockData
     * @param array $importedData
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterProcess(
        StockItemProcessorInterface $subject,
        mixed $result,
        array $stockData,
        array $importedData
    ): void {
        $sourceItems = [];
        $skus = [];

        $isSingleSourceMode = $this->isSingleSourceMode->execute();
        $existingSourceItemsBySKU = $isSingleSourceMode && !$this->someRowOmitsQuantity($stockData, $importedData)
            ? []
            : $this->getSourceItems(array_keys($stockData));
        $defaultSourceCode = $this->defaultSourceProvider->getCode();
        $sourceItemIds = [];
        foreach ($stockData as $sku => $stockDatum) {
            $sku = (string)$sku;
            $skus[] = $sku;
            $sources = $existingSourceItemsBySKU[$sku] ?? [];
            $importedRow = $importedData[$sku] ?? [];
            $hasDefaultSource = isset($sources[$defaultSourceCode]);

            if ($this->shouldWriteDefaultSourceItem($sku, $importedRow, $hasDefaultSource, $isSingleSourceMode)) {
                $sourceItem = $this->sourceItemFactory->create();
                $sourceItem->setSku($sku);
                $sourceItem->setSourceCode($defaultSourceCode);
                $sourceItem->setQuantity(
                    $this->resolveDefaultSourceQuantity(
                        $stockDatum,
                        $sources[$defaultSourceCode] ?? null,
                        $importedRow
                    )
                );
                $sourceItem->setStatus((int) ($stockDatum['is_in_stock'] ?? 0));
                $sourceItems[] = $sourceItem;
            }

            unset($sources[$defaultSourceCode]);
            // Is there any other source (except the default source) assigned to the product
            if (count($sources) > 0
                && array_filter(
                    array_intersect_key($importedRow, self::STOCK_CONFIGURATION_FIELDS),
                    fn ($value) => $value !== null
                )
            ) {
                array_push(
                    $sourceItemIds,
                    ...array_column(array_values($sources), SourceItemResourceModel::ID_FIELD_NAME)
                );
            }
        }
        if (count($sourceItems) > 0) {
            $this->sourceItemsSave->execute($sourceItems);
        }
        // Reindex non default source items if global stock configuration such as backorders have changed
        if (!empty($sourceItemIds)) {
            $this->sourceItemIndexer->executeList($sourceItemIds);
        }

        // Reindex composite products present in data.
        // As they don't have their own source items, no reindex will be triggered automatically.
        $this->compositeProductsIndexer->reindexList($skus);
    }

    /**
     * Checks whether default source item should be updated for the given SKU
     *
     * Prevent products to be assigned to `default` source unless
     *
     * - The product is new
     * - The qty is explicitly set in the import file
     * - Only one source exists (single source mode)
     * - The product is already assigned to the default source
     *
     * @param string $sku
     * @param bool $hasQty
     * @param bool $hasDefaultSource
     * @param bool $isSingleSourceMode
     * @return bool
     */
    private function shouldUpdateDefaultSourceItem(
        string $sku,
        bool $hasQty,
        bool $hasDefaultSource,
        bool $isSingleSourceMode
    ): bool {
        return !$this->skuStorage->has($sku) || $hasQty || $isSingleSourceMode || $hasDefaultSource;
    }

    /**
     * Decide whether the default source item of a sku has to be written for this import row.
     *
     * @param string $sku
     * @param array $importedRow
     * @param bool $hasDefaultSource
     * @param bool $isSingleSourceMode
     * @return bool
     */
    private function shouldWriteDefaultSourceItem(
        string $sku,
        array $importedRow,
        bool $hasDefaultSource,
        bool $isSingleSourceMode
    ): bool {
        if (!$this->skuStorage->has($sku)) {
            return true;
        }

        if (!array_intersect_key($importedRow, self::STOCK_ROW_FIELDS)) {
            return false;
        }

        return $this->shouldUpdateDefaultSourceItem(
            $sku,
            (bool) ($importedRow['qty'] ?? false),
            $hasDefaultSource,
            $isSingleSourceMode
        );
    }

    /**
     * Resolve the quantity to write, keeping the stored one when the import carries none.
     *
     * @param array $stockDatum
     * @param array|null $defaultSourceItem
     * @param array $importedRow
     * @return float
     */
    private function resolveDefaultSourceQuantity(
        array $stockDatum,
        ?array $defaultSourceItem,
        array $importedRow
    ): float {
        if (!$this->importCarriesQuantity($importedRow) && $defaultSourceItem !== null) {
            return (float) ($defaultSourceItem[SourceItemInterface::QUANTITY] ?? 0);
        }

        return (float) ($stockDatum['qty'] ?? 0);
    }

    /**
     * Tell whether any row of the import omits its quantity.
     *
     * @param array $stockData
     * @param array $importedData
     * @return bool
     */
    private function someRowOmitsQuantity(array $stockData, array $importedData): bool
    {
        foreach (array_keys($stockData) as $sku) {
            if (!$this->importCarriesQuantity($importedData[(string) $sku] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tell whether an import row carries a usable quantity.
     *
     * @param array $importedRow
     * @return bool
     */
    private function importCarriesQuantity(array $importedRow): bool
    {
        return isset($importedRow['qty']) && $importedRow['qty'] !== '';
    }

    /**
     * Fetch product's source items
     *
     * @param array $skus
     * @return array
     */
    private function getSourceItems(array $skus): array
    {
        $fields = [
            SourceItemResourceModel::ID_FIELD_NAME,
            SourceItemInterface::SOURCE_CODE,
            SourceItemInterface::SKU,
            SourceItemInterface::QUANTITY
        ];
        $result = [];
        foreach ($this->sourceItemResourceModel->findAllBySkus($skus, $fields) as $item) {
            $result[$item[SourceItemInterface::SKU]][$item[SourceItemInterface::SOURCE_CODE]] = $item;
        }
        return $result;
    }
}
