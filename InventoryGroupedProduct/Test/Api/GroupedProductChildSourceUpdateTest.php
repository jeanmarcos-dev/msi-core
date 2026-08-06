<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryGroupedProduct\Test\Api;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Webapi\Rest\Request;
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test source and stock changes of child product.
 */
class GroupedProductChildSourceUpdateTest extends WebapiAbstract
{
    private const DEFAULT_STOCK_ID = 1;

    private const SOURCE_ITEM_RESOURCE_PATH = '/V1/inventory/source-items';
    private const SOURCE_ITEM_SERVICE_NAME_SAVE = 'inventoryApiSourceItemsSaveV1';
    private const SOURCE_ITEM_SERVICE_NAME_DELETE = 'inventoryApiSourceItemsDeleteV1';

    /**
     * @var array[]
     */
    private $sourceItems = [
        [
            SourceItemInterface::SOURCE_CODE => 'eu-1',
            SourceItemInterface::SKU => 'SKU-4',
            SourceItemInterface::QUANTITY => 10,
            SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
        ],
        [
            SourceItemInterface::SOURCE_CODE => 'eu-2',
            SourceItemInterface::SKU => 'SKU-4',
            SourceItemInterface::QUANTITY => 20,
            SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
        ],
    ];

    /**
     * @var AreProductsSalableInterface
     */
    private $areProductsSalable;

    /**
     * @var \Magento\TestFramework\Fixture\DataFixtureStorage
     */
    private $fixtures;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->areProductsSalable = Bootstrap::getObjectManager()->get(AreProductsSalableInterface::class);
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->deleteSourceItems($this->sourceItems);
        parent::tearDown();
    }

    #[
        DataFixture(ProductFixture::class, as: 'p1'),
        DataFixture(
            GroupedProductFixture::class,
            ['sku' => 'gr1', 'product_links' => ['$p1$']],
            'gr1'
        ),
    ]
    public function testGroupedProductStockStatusShouldBeUpdatedWhenChildProductStockStatusChange(): void
    {
        $groupedProductSku = $this->fixtures->get('gr1')->getSku();
        $simpleProductSku = $this->fixtures->get('p1')->getSku();
        $this->assertTrue($this->isSalable($groupedProductSku));
        $sources = [
            [
                SourceItemInterface::SOURCE_CODE => 'default',
                SourceItemInterface::SKU => $simpleProductSku,
                SourceItemInterface::QUANTITY => 99,
                SourceItemInterface::STATUS => SourceItemInterface::STATUS_OUT_OF_STOCK,
            ]
        ];
        $this->sourceItems = array_merge($this->sourceItems, $sources);
        $this->addSourceItems($sources);
        $this->assertFalse($this->isSalable($groupedProductSku));
        $sources = [
            [
                SourceItemInterface::SOURCE_CODE => 'default',
                SourceItemInterface::SKU => $simpleProductSku,
                SourceItemInterface::QUANTITY => 99,
                SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
            ]
        ];
        $this->addSourceItems($sources);
        $this->assertTrue($this->isSalable($groupedProductSku));
    }

    /**
     * @param array $sourceItems
     */
    private function addSourceItems(array $sourceItems): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::SOURCE_ITEM_RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SOURCE_ITEM_SERVICE_NAME_SAVE,
                'operation' => self::SOURCE_ITEM_SERVICE_NAME_SAVE . 'Execute',
            ],
        ];
        $this->_webApiCall($serviceInfo, ['sourceItems' => $sourceItems]);
    }

    /**
     * @param array $sourceItems
     */
    private function deleteSourceItems(array $sourceItems): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::SOURCE_ITEM_RESOURCE_PATH . '-delete',
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SOURCE_ITEM_SERVICE_NAME_DELETE,
                'operation' => self::SOURCE_ITEM_SERVICE_NAME_DELETE . 'Execute',
            ],
        ];
        $this->_webApiCall($serviceInfo, ['sourceItems' => $sourceItems]);
    }

    /**
     * Read the salability of a composite the way the storefront does, from the MSI index.
     *
     * @param string $sku
     * @return bool
     */
    private function isSalable(string $sku): bool
    {
        return current($this->areProductsSalable->execute([$sku], self::DEFAULT_STOCK_ID))->isSalable();
    }
}
