<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Test\Integration\Model\SourceItem\Command\Handler;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class SourceItemsSaveHandlerSourceCodeCaseTest extends TestCase
{
    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var GetSourceItemsBySkuInterface
     */
    private $getSourceItemsBySku;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->fixtures = Bootstrap::getObjectManager()->get(DataFixtureStorageManager::class)->getStorage();
        $this->sourceItemsSave = Bootstrap::getObjectManager()->create(SourceItemsSaveInterface::class);
        $this->getSourceItemsBySku = Bootstrap::getObjectManager()->create(GetSourceItemsBySkuInterface::class);
    }

    #[
        DataFixture(SourceFixture::class, ['source_code' => 'warehouse_bravo'], 'source'),
        DataFixture(ProductFixture::class, [], 'product'),
    ]
    public function testSourceItemWithSourceCodeDifferingOnlyInCaseIsRejected(): void
    {
        $sku = $this->fixtures->get('product')->getSku();

        $dataObjectHelper = Bootstrap::getObjectManager()->create(DataObjectHelper::class);
        $data = [
            SourceItemInterface::SOURCE_CODE => 'Warehouse_Bravo',
            SourceItemInterface::SKU => $sku,
            SourceItemInterface::QUANTITY => 25,
            SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
        ];

        $sourceItemFactory = Bootstrap::getObjectManager()->get(SourceItemInterfaceFactory::class);
        $sourceItem = $sourceItemFactory->create();
        $dataObjectHelper->populateWithArray($sourceItem, $data, SourceItemInterface::class);

        try {
            $this->sourceItemsSave->execute([$sourceItem]);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $exception) {
            self::assertStringContainsString(
                'Source code "Warehouse_Bravo" does not match the stored source code "warehouse_bravo".',
                $exception->getMessage()
            );
        }

        $storedSourceCodes = array_map(
            static fn (SourceItemInterface $sourceItem): string => mb_strtolower($sourceItem->getSourceCode()),
            $this->getSourceItemsBySku->execute($sku)
        );

        self::assertSame('Warehouse_Bravo', $sourceItem->getSourceCode());
        self::assertNotContains('warehouse_bravo', $storedSourceCodes);
    }
}
