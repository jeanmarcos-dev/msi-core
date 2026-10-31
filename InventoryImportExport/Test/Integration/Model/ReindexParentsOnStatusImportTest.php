<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Test\Integration\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogImportExport\Model\Import\ProductFactory;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Source\Csv;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ReindexParentsOnStatusImportTest extends TestCase
{
    private const DEFAULT_STOCK_ID = 1;

    private const IMPORT_FILE = 'msi_status_import.csv';

    /**
     * @var ProductFactory
     */
    private ProductFactory $importerFactory;

    /**
     * @var WriteInterface
     */
    private WriteInterface $varDirectory;

    /**
     * @var GetStockItemData
     */
    private GetStockItemData $getStockItemData;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->importerFactory = $objectManager->get(ProductFactory::class);
        $this->varDirectory = $objectManager->get(Filesystem::class)->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->getStockItemData = $objectManager->get(GetStockItemData::class);
    }

    protected function tearDown(): void
    {
        if ($this->varDirectory->isExist(self::IMPORT_FILE)) {
            $this->varDirectory->delete(self::IMPORT_FILE);
        }
    }

    #[
        AppArea('adminhtml'),
        DbIsolation(false),
        DataFixture(ProductFixture::class, as: 'child'),
        DataFixture(AttributeFixture::class, as: 'attr'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attr$'], '_links' => ['$child$']],
            'parent'
        ),
        DataFixture(IndexerFixture::class),
    ]
    public function testImportingAChildStatusReindexesItsParentInBothDirections(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $childSku = $fixtures->get('child')->getSku();
        $parentSku = $fixtures->get('parent')->getSku();
        self::assertSame(1, $this->getParentIsSalable($parentSku));

        $this->importStatus($childSku, Status::STATUS_DISABLED);
        self::assertSame(
            0,
            $this->getParentIsSalable($parentSku),
            'disabling the only child through an import must make the parent unsalable'
        );

        $this->importStatus($childSku, Status::STATUS_ENABLED);
        self::assertSame(
            1,
            $this->getParentIsSalable($parentSku),
            're-enabling the child through an import must make the parent salable again'
        );
    }

    private function importStatus(string $sku, int $status): void
    {
        $this->varDirectory->writeFile(self::IMPORT_FILE, "sku,status\n$sku,$status\n");
        $importer = $this->importerFactory->create();
        $importer->setParameters(['behavior' => Import::BEHAVIOR_APPEND, 'entity' => 'catalog_product']);
        $importer->setSource(
            Bootstrap::getObjectManager()->create(
                Csv::class,
                ['file' => self::IMPORT_FILE, 'directory' => $this->varDirectory]
            )
        );
        $errors = $importer->validateData();
        self::assertSame(0, $errors->getErrorsCount(), 'the import file must be valid');
        $importer->importData();
    }

    private function getParentIsSalable(string $parentSku): int
    {
        $row = $this->getStockItemData->execute($parentSku, self::DEFAULT_STOCK_ID);

        return (int) $row[GetStockItemDataInterface::IS_SALABLE];
    }
}
