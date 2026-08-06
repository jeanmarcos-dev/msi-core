<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Test\Integration\Indexer;

use Magento\Bundle\Test\Fixture\Option as BundleOptionFixture;
use Magento\Bundle\Test\Fixture\Product as BundleProductFixture;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogInventory\Model\Stock;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A bundle is only salable while every required option can still contribute a selection.
 */
#[DbIsolation(false)]
class OptionAvailabilityTest extends TestCase
{
    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var GetStockItemData
     */
    private $getStockItemData;

    protected function setUp(): void
    {
        $this->fixtures = DataFixtureStorageManager::getStorage();
        $this->getStockItemData = Bootstrap::getObjectManager()->get(GetStockItemData::class);
    }

    #[
        DataFixture(ProductFixture::class, as: 'inStock'),
        DataFixture(ProductFixture::class, ['stock_item' => ['qty' => 0]], 'outOfStock'),
        DataFixture(ProductFixture::class, ['status' => Status::STATUS_DISABLED], 'disabled'),
        DataFixture(BundleOptionFixture::class, ['product_links' => ['$inStock$']], 'availableOption'),
        DataFixture(BundleOptionFixture::class, ['product_links' => ['$outOfStock$']], 'emptyOption'),
        DataFixture(BundleOptionFixture::class, ['product_links' => ['$disabled$']], 'disabledOption'),
        DataFixture(
            BundleProductFixture::class,
            ['sku' => 'bundle-all-available', '_options' => ['$availableOption$']],
            'available'
        ),
        DataFixture(
            BundleProductFixture::class,
            ['sku' => 'bundle-with-empty-option', '_options' => ['$availableOption$', '$emptyOption$']],
            'withEmptyOption'
        ),
        DataFixture(
            BundleProductFixture::class,
            ['sku' => 'bundle-with-disabled-option', '_options' => ['$availableOption$', '$disabledOption$']],
            'withDisabledOption'
        ),
    ]
    public function testARequiredOptionWithNothingToSellMakesTheBundleUnsalable(): void
    {
        self::assertSame(1, $this->getIndexedSalability('bundle-all-available'));

        // The out of stock selection carries no index row, and an option whose only selection is missing
        // used to disappear from the result along with its veto.
        self::assertSame(0, $this->getIndexedSalability('bundle-with-empty-option'));

        self::assertSame(0, $this->getIndexedSalability('bundle-with-disabled-option'));
    }

    /**
     * Read the indexed salability of a sku in the Default Stock.
     *
     * @param string $sku
     * @return int
     */
    private function getIndexedSalability(string $sku): int
    {
        $indexData = $this->getStockItemData->execute($sku, Stock::DEFAULT_STOCK_ID);
        self::assertNotNull($indexData, sprintf('The sku "%s" is missing from the index.', $sku));

        return (int) $indexData[GetStockItemDataInterface::IS_SALABLE];
    }
}
