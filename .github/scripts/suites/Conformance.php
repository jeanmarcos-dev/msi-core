<?php
declare(strict_types=1);

namespace Suites;

class Conformance
{
    public const CNF_MIRROR_SOURCE = 'cnf_mirror';
    public const CNF_MIRROR_STOCK_NAME = 'Conformance Mirror';
    public const CNF_DEFAULT_SOURCE = 'default';
    public const CNF_DEFAULT_STOCK_ID = 1;
    public const CNF_SIMPLE = 'cnf_simple';
    public const CNF_CHILD_A = 'cnf_child_a';
    public const CNF_CHILD_B = 'cnf_child_b';
    public const CNF_CONFIG = 'cnf_config';
    public const CNF_GROUPED = 'cnf_grouped';
    public const CNF_BUNDLE = 'cnf_bundle';
    public const CNF_VARIANT_ATTRIBUTE = 'cnf_variant';
    public const CNF_SECOND_DEFAULT_SOURCE = 'cnf_second';

    /**
     * @var int
     */
    private static int $checks = 0;

    /**
     * @var int
     */
    private static int $failures = 0;

    public static function compare(string $scenario, string $probe, $default, $mirror): bool
    {
        self::$checks++;
        $same = json_encode($default) === json_encode($mirror);
        if (!$same) {
            self::$failures++;
        }
        printf(
            "  %s %-22s %-34s default=%s mirror=%s\n",
            $same ? 'ok' : 'XX',
            $scenario,
            $probe,
            json_encode($default),
            json_encode($mirror)
        );

        return $same;
    }

    public static function ensureMirrorStock($om): int
    {
        $sourceRepo = $om->get(\Magento\InventoryApi\Api\SourceRepositoryInterface::class);
        try {
            $sourceRepo->get(self::CNF_MIRROR_SOURCE);
        } catch (\Throwable $e) {
            $source = $om->get(\Magento\InventoryApi\Api\Data\SourceInterfaceFactory::class)->create();
            $source->setSourceCode(self::CNF_MIRROR_SOURCE);
            $source->setName('Conformance Mirror Source');
            $source->setEnabled(true);
            $source->setCountryId('US');
            $source->setPostcode('00000');
            $sourceRepo->save($source);
        }

        $stockRepo = $om->get(\Magento\InventoryApi\Api\StockRepositoryInterface::class);
        $criteria = $om->get(\Magento\Framework\Api\SearchCriteriaBuilder::class)
            ->addFilter('name', self::CNF_MIRROR_STOCK_NAME)
            ->create();
        $found = $stockRepo->getList($criteria)->getItems();
        if ($found) {
            $stockId = (int)reset($found)->getStockId();
        } else {
            $stock = $om->get(\Magento\InventoryApi\Api\Data\StockInterfaceFactory::class)->create();
            $stock->setName(self::CNF_MIRROR_STOCK_NAME);
            $stockId = (int)$stockRepo->save($stock);
        }

        $link = $om->get(\Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory::class)->create();
        $link->setStockId($stockId);
        $link->setSourceCode(self::CNF_MIRROR_SOURCE);
        $link->setPriority(1);
        $om->get(\Magento\InventoryApi\Api\StockSourceLinksSaveInterface::class)->execute([$link]);

        return $stockId;
    }

    public static function ensureVariantAttribute($om): array
    {
        $attributeRepo = $om->get(\Magento\Catalog\Api\ProductAttributeRepositoryInterface::class);
        try {
            $attribute = $attributeRepo->get(self::CNF_VARIANT_ATTRIBUTE);
        } catch (\Throwable $e) {
            $attribute = $om->create(\Magento\Catalog\Model\ResourceModel\Eav\Attribute::class);
            $attribute->setData(
                [
                    'attribute_code' => self::CNF_VARIANT_ATTRIBUTE,
                    'entity_type_id' => 4,
                    'frontend_input' => 'select',
                    'frontend_label' => 'Conformance Variant',
                    'backend_type' => 'int',
                    'is_global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_user_defined' => 1,
                    'is_required' => 0,
                    'is_unique' => 0,
                    'is_configurable' => 1,
                    'used_in_product_listing' => 1,
                    'option' => [
                        'value' => ['opt_a' => ['Variant A'], 'opt_b' => ['Variant B']],
                        'order' => ['opt_a' => 1, 'opt_b' => 2],
                    ],
                ]
            );
            $attribute = $attributeRepo->save($attribute);
        }

        $attributeId = (int)$attribute->getAttributeId();
        self::assignAttributeToDefaultSet($om, $attributeId);

        $optionIds = [];
        foreach ($attributeRepo->get(self::CNF_VARIANT_ATTRIBUTE)->getOptions() as $option) {
            if ($option->getValue() !== '' && $option->getValue() !== null) {
                $optionIds[] = (int)$option->getValue();
            }
        }
        sort($optionIds);

        return [$attributeId, $optionIds];
    }

    public static function assignAttributeToDefaultSet($om, int $attributeId): void
    {
        $resource = $om->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resource->getConnection();
        $exists = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('eav_entity_attribute'), ['entity_attribute_id'])
                ->where('attribute_set_id = ?', 4)
                ->where('attribute_id = ?', $attributeId)
        );
        if ($exists) {
            return;
        }
        $groupId = (int)$connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('eav_attribute_group'), ['attribute_group_id'])
                ->where('attribute_set_id = ?', 4)
                ->order('sort_order ASC')
                ->limit(1)
        );
        $om->get(\Magento\Catalog\Api\ProductAttributeManagementInterface::class)
            ->assign(4, $groupId, self::CNF_VARIANT_ATTRIBUTE, 100);
    }

    public static function ensureProduct($om, string $sku, string $type, array $extra = []): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        try {
            $product = $productRepo->get($sku, true, 0, true);
        } catch (\Throwable $e) {
            $product = $om->create(\Magento\Catalog\Model\Product::class);
            $product->setSku($sku);
            $product->setAttributeSetId(4);
        }
        $product->setName('Conformance ' . $sku);
        $product->setTypeId($type);
        $product->setPrice($extra['price'] ?? 10.0);
        $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        $product->setWebsiteIds([1]);
        if (isset($extra['variant'])) {
            $product->setData(self::CNF_VARIANT_ATTRIBUTE, $extra['variant']);
        }
        if ($type === 'simple') {
            $product->setStockData(
                [
                    'use_config_manage_stock' => 1,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => $extra['qty'] ?? 100,
                ]
            );
        }
        $productRepo->save($product);
    }

    public static function setProductStatus($om, string $sku, bool $enabled): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $product = $productRepo->get($sku, true, 0, true);
        $product->setStatus(
            $enabled
                ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
                : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED
        );
        $productRepo->save($product);
    }

    public static function attachSecondSourceToDefaultStock(
        $om
    ): \Magento\InventoryApi\Api\Data\StockSourceLinkInterface {
        $sourceRepo = $om->get(\Magento\InventoryApi\Api\SourceRepositoryInterface::class);
        try {
            $sourceRepo->get(self::CNF_SECOND_DEFAULT_SOURCE);
        } catch (\Throwable $notFound) {
            $source = $om->get(\Magento\InventoryApi\Api\Data\SourceInterfaceFactory::class)->create();
            $source->setSourceCode(self::CNF_SECOND_DEFAULT_SOURCE);
            $source->setName('Conformance Second');
            $source->setEnabled(true);
            $source->setPostcode('00000');
            $source->setCountryId('US');
            $sourceRepo->save($source);
        }

        $link = $om->get(\Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory::class)->create();
        $link->setStockId(self::CNF_DEFAULT_STOCK_ID);
        $link->setSourceCode(self::CNF_SECOND_DEFAULT_SOURCE);
        $link->setPriority(20);
        $om->get(\Magento\InventoryApi\Api\StockSourceLinksSaveInterface::class)->execute([$link]);

        return $link;
    }

    public static function detachSecondSourceFromDefaultStock($om, $link, string $sku): void
    {
        $item = $om->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class)->create();
        $item->setSku($sku);
        $item->setSourceCode(self::CNF_SECOND_DEFAULT_SOURCE);
        $om->get(\Magento\InventoryApi\Api\SourceItemsDeleteInterface::class)->execute([$item]);
        $om->get(\Magento\InventoryApi\Api\StockSourceLinksDeleteInterface::class)->execute([$link]);
    }

    public static function setSourceQty($om, string $sku, string $sourceCode, float $qty, int $status = 1): void
    {
        $item = $om->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class)->create();
        $item->setSku($sku);
        $item->setSourceCode($sourceCode);
        $item->setQuantity($qty);
        $item->setStatus($status);
        $om->get(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class)->execute([$item]);
    }

    public static function sourceQuantities($om, $resource, string $sku, array $sourceCodes): array
    {
        $conn = $resource->getConnection();
        $rows = $conn->fetchPairs(
            $conn->select()->from($resource->getTableName('inventory_source_item'), ['source_code', 'quantity'])
                ->where('sku = ?', $sku)->where('source_code IN (?)', $sourceCodes)
        );
        ksort($rows);

        return array_map('floatval', $rows);
    }

    public static function massSetProductStatus($om, array $skus, bool $enabled): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $ids = [];
        foreach ($skus as $sku) {
            $ids[] = (int)$productRepo->get($sku, false, 0, true)->getId();
        }
        $om->get(\Magento\Catalog\Model\Product\Action::class)->updateAttributes(
            $ids,
            ['status' => $enabled
                ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
                : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED],
            0
        );
    }

    public static function linkConfigurable($om, string $parentSku, array $childSkus, int $attributeId): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $parent = $productRepo->get($parentSku, true, 0, true);
        $optionValueFactory = $om->get(\Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory::class);

        $childIds = [];
        $values = [];
        foreach ($childSkus as $childSku) {
            $child = $productRepo->get($childSku, false, 0, true);
            $childIds[] = (int)$child->getId();
            $value = $optionValueFactory->create();
            $value->setValueIndex((int)$child->getData(self::CNF_VARIANT_ATTRIBUTE));
            $values[] = $value;
        }

        $option = $om->get(\Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory::class)->create();
        $option->setAttributeId((string)$attributeId);
        $option->setLabel('Conformance Variant');
        $option->setPosition(0);
        $option->setValues($values);

        $extension = $parent->getExtensionAttributes();
        $extension->setConfigurableProductOptions([$option]);
        $extension->setConfigurableProductLinks($childIds);
        $parent->setExtensionAttributes($extension);
        $productRepo->save($parent);
    }

    public static function linkGrouped($om, string $parentSku, array $childSkus): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $parent = $productRepo->get($parentSku, true, 0, true);
        $linkFactory = $om->get(\Magento\Catalog\Api\Data\ProductLinkInterfaceFactory::class);

        $links = [];
        $position = 1;
        foreach ($childSkus as $childSku) {
            $link = $linkFactory->create();
            $link->setSku($parentSku);
            $link->setLinkedProductSku($childSku);
            $link->setLinkType('associated');
            $link->setPosition($position++);
            $link->setExtensionAttributes($link->getExtensionAttributes()->setQty(1));
            $links[] = $link;
        }
        $parent->setProductLinks($links);
        $productRepo->save($parent);
    }

    public static function setMirroredQty($om, string $sku, float $qty, int $status = 1): void
    {
        $factory = $om->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class);
        $items = [];
        foreach ([self::CNF_DEFAULT_SOURCE, self::CNF_MIRROR_SOURCE] as $code) {
            $item = $factory->create();
            $item->setSku($sku);
            $item->setSourceCode($code);
            $item->setQuantity($qty);
            $item->setStatus($status);
            $items[] = $item;
        }
        $om->get(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class)->execute($items);
    }

    public static function setStockConfig($om, string $sku, array $data): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $product = $productRepo->get($sku, true, 0, true);
        $product->setStockData($data);
        $productRepo->save($product);
    }

    public static function probe($om, string $sku, int $stockId): array
    {
        $out = [];

        try {
            $row = $om->get(\Magento\InventorySalesApi\Model\GetStockItemDataInterface::class)->execute($sku, $stockId);
            $out['index.quantity'] = $row === null ? null : (float)$row['quantity'];
            $out['index.is_salable'] = $row === null ? null : (int)$row['is_salable'];
        } catch (\Throwable $e) {
            $out['index.quantity'] = 'ERR:' . $e->getMessage();
            $out['index.is_salable'] = 'ERR';
        }

        try {
            $out['isProductSalable'] = (bool)$om->get(\Magento\InventorySalesApi\Api\IsProductSalableInterface::class)
                ->execute($sku, $stockId);
        } catch (\Throwable $e) {
            $out['isProductSalable'] = 'ERR:' . $e->getMessage();
        }

        try {
            $out['salableQty'] = (float)$om->get(\Magento\InventorySalesApi\Api\GetProductSalableQtyInterface::class)
                ->execute($sku, $stockId);
        } catch (\Throwable $e) {
            $out['salableQty'] = 'ERR:' . $e->getMessage();
        }

        foreach ([1.0, 999999.0] as $qty) {
            $key = 'salableForQty(' . (int)$qty . ')';
            try {
                $out[$key] = (bool)$om
                    ->get(\Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface::class)
                    ->execute($sku, $stockId, $qty)
                    ->isSalable();
            } catch (\Throwable $e) {
                $out[$key] = 'ERR:' . $e->getMessage();
            }
        }

        try {
            $results = $om->get(\Magento\InventorySalesApi\Api\AreProductsSalableInterface::class)
                ->execute([$sku], $stockId);
            $first = reset($results);
            $out['areProductsSalable'] = $first === false ? null : (bool)$first->isSalable();
        } catch (\Throwable $e) {
            $out['areProductsSalable'] = 'ERR:' . $e->getMessage();
        }

        try {
            $config = $om->get(\Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface::class)
                ->execute($sku, $stockId);
            $out['config.manageStock'] = (bool)$config->isManageStock();
            $out['config.backorders'] = (int)$config->getBackorders();
            $out['config.minQty'] = (float)$config->getMinQty();
            $out['config.maxSaleQty'] = (float)$config->getMaxSaleQty();
        } catch (\Throwable $e) {
            $out['config.manageStock'] = 'ERR:' . $e->getMessage();
        }

        return $out;
    }

    public static function assertSalableInBothStocks(
        $om,
        string $scenario,
        string $sku,
        int $mirrorStockId,
        int $expected
    ): void {
        foreach ([self::CNF_DEFAULT_STOCK_ID => 'stock1', $mirrorStockId => 'mirror'] as $stockId => $label) {
            $row = $om->get(\Magento\InventorySalesApi\Model\GetStockItemDataInterface::class)->execute($sku, $stockId);
            self::assert(
                $scenario,
                $sku . '::is_salable[' . $label . ']',
                $expected,
                $row === null ? null : (int)$row['is_salable']
            );
        }
    }

    public static function assert(string $scenario, string $probe, $expected, $actual): bool
    {
        self::$checks++;
        $same = json_encode($expected) === json_encode($actual);
        if (!$same) {
            self::$failures++;
        }
        printf(
            "  %s %-22s %-34s expected=%s actual=%s\n",
            $same ? 'ok' : 'XX',
            $scenario,
            $probe,
            json_encode($expected),
            json_encode($actual)
        );

        return $same;
    }

    public static function compareStocks($om, string $scenario, string $sku, int $mirrorStockId): void
    {
        $default = self::probe($om, $sku, self::CNF_DEFAULT_STOCK_ID);
        $mirror = self::probe($om, $sku, $mirrorStockId);
        foreach (array_keys($default + $mirror) as $probe) {
            self::compare(
                $scenario,
                $sku . '::' . $probe,
                array_key_exists($probe, $default) ? $default[$probe] : '<no-probe>',
                array_key_exists($probe, $mirror) ? $mirror[$probe] : '<no-probe>'
            );
        }
    }

    public static function reindex($om): void
    {
        $registry = $om->get(\Magento\Framework\Indexer\IndexerRegistry::class);
        $registry->get('inventory')->reindexAll();
        $registry->get('cataloginventory_stock')->reindexAll();
    }

    public static function appendMirroredReservation(
        $om,
        string $sku,
        float $quantity,
        int $mirrorStockId,
        string $tag
    ): void {
        $builder = $om->get(\Magento\InventoryReservationsApi\Model\ReservationBuilderInterface::class);
        $reservations = [];
        foreach ([self::CNF_DEFAULT_STOCK_ID, $mirrorStockId] as $stockId) {
            $reservations[] = $builder->setSku($sku)
                ->setStockId($stockId)
                ->setQuantity($quantity)
                ->setMetadata($tag)
                ->build();
        }
        $om->get(\Magento\InventoryReservationsApi\Model\AppendReservationsInterface::class)->execute($reservations);
    }

    public static function sourceItemStatus($om, string $sku, string $sourceCode): ?int
    {
        $resource = $om->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resource->getConnection();
        $status = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('inventory_source_item'), ['status'])
                ->where('sku = ?', $sku)
                ->where('source_code = ?', $sourceCode)
        );

        return $status === false ? null : (int)$status;
    }

    public static function indexTableType($om, $resource, int $stockId): string
    {
        $table = $om->get(\Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface::class)
            ->execute($stockId);
        $row = $resource->getConnection()->fetchRow('SHOW FULL TABLES LIKE ?', [$table]);

        return $row === false ? 'ABSENT' : (string)array_values($row)[1];
    }

    public static function ensureBundle($om, string $sku, array $childSkus): void
    {
        $productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        try {
            $bundle = $productRepo->get($sku, true, 0, true);
        } catch (\Throwable $e) {
            $bundle = $om->create(\Magento\Catalog\Model\Product::class);
            $bundle->setSku($sku);
            $bundle->setName('Conformance Bundle');
            $bundle->setTypeId(\Magento\Bundle\Model\Product\Type::TYPE_CODE);
            $bundle->setAttributeSetId(4);
            $bundle->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $bundle->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
            $bundle->setWebsiteIds([1]);
            $bundle->setPriceType(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC);
            
            $bundle->setPriceView(0);
            $bundle->setSkuType(0);
            $bundle->setWeightType(0);
            
            $bundle->setShipmentType(1);
            $bundle->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1]);
        }

        $extension = $bundle->getExtensionAttributes();
        if ($extension->getBundleProductOptions()) {
            return;
        }

        $optionFactory = $om->get(\Magento\Bundle\Api\Data\OptionInterfaceFactory::class);
        $linkFactory = $om->get(\Magento\Bundle\Api\Data\LinkInterfaceFactory::class);

        $links = [];
        foreach ($childSkus as $childSku) {
            $link = $linkFactory->create();
            $link->setSku($childSku);
            $link->setQty(1);
            $link->setCanChangeQuantity(0);
            $links[] = $link;
        }

        $option = $optionFactory->create();
        $option->setTitle('Conformance Option');
        $option->setType('checkbox');
        $option->setRequired(true);
        $option->setSku($sku);
        $option->setProductLinks($links);

        $extension->setBundleProductOptions([$option]);
        $bundle->setExtensionAttributes($extension);
        $productRepo->save($bundle);
    }

    public static function bundleSelectionSkus($om, string $bundleSku): array
    {
        $bundle = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class)->get($bundleSku, false, 1, true);
        $type = $bundle->getTypeInstance();
        $collection = $type->getSelectionsCollection($type->getOptionsIds($bundle), $bundle);
        $collection->addQuantityFilter();

        $skus = [];
        foreach ($collection as $selection) {
            $skus[] = $selection->getSku();
        }
        sort($skus);

        return $skus;
    }

    public static function currentWebsiteStockId($om): int
    {
        $websiteId = (int)$om->get(\Magento\Store\Model\StoreManagerInterface::class)->getWebsite()->getId();

        return (int)$om->get(\Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface::class)
            ->execute($websiteId)->getStockId();
    }

    public static function salableSkus($om, array $skus, int $stockId): array
    {
        $salable = [];
        $areSalable = $om->get(\Magento\InventorySalesApi\Api\AreProductsSalableInterface::class);
        foreach ($areSalable->execute($skus, $stockId) as $r) {
            if ($r->isSalable()) {
                $salable[] = $r->getSku();
            }
        }
        sort($salable);

        return $salable;
    }

    public static function gridStockColumns($om, array $skus): array
    {
        static $configured = false;
        if (!$configured) {
            $om->configure($om->get(\Magento\Framework\App\ObjectManager\ConfigLoader::class)->load('adminhtml'));
            $configured = true;
        }

        $provider = $om->create(
            \Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider::class,
            ['name' => 'product_listing_data_source', 'primaryFieldName' => 'entity_id', 'requestFieldName' => 'id']
        );
        $provider->addField('qty');
        $provider->addField('quantity_and_stock_status');

        $collection = $provider->getCollection();
        $collection->addAttributeToSelect('sku');
        $collection->addFieldToFilter('sku', ['in' => $skus]);

        $columns = [];
        foreach ($collection as $product) {
            $columns[$product->getSku()] = [
                'qty' => (float)$product->getData('qty'),
                'is_salable' => (int)$product->getData('quantity_and_stock_status'),
            ];
        }
        ksort($columns);

        return $columns;
    }

    public static function indexColumns($om, array $skus, int $stockId): array
    {
        $getStockItemData = $om->get(\Magento\InventorySalesApi\Model\GetStockItemDataInterface::class);

        $columns = [];
        foreach ($skus as $sku) {
            $data = $getStockItemData->execute($sku, $stockId);
            $columns[$sku] = [
                'qty' => (float)$data[\Magento\InventorySalesApi\Model\GetStockItemDataInterface::QUANTITY],
                'is_salable' => (int)$data[\Magento\InventorySalesApi\Model\GetStockItemDataInterface::IS_SALABLE],
            ];
        }
        ksort($columns);

        return $columns;
    }

    public static function verdict(): int
    {
        $checks = self::$checks;
        $failures = self::$failures;
        printf(
            "\n%s conformance: %d/%d probes identical, %d divergences\n",
            $failures === 0 ? 'CONFORMANT' : 'DIVERGENT',
            $checks - $failures,
            $checks,
            $failures
        );

        return $failures === 0 ? 0 : 1;
    }
}
