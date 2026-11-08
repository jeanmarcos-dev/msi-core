<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Integration\Website;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Registry;
use Magento\Inventory\Model\Stock\Command\GetInterface as GetStockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\InventorySalesApi\Model\ReplaceSalesChannelsForStockInterface;
use Magento\Store\Model\WebsiteFactory;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    DataFixture('Magento_InventoryApi::Test/_files/sources.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stocks.php'),
    DataFixture('Magento_InventoryApi::Test/_files/stock_source_links.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/websites_with_stores.php'),
    DataFixture('Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php'),
]
class WebsitesWithoutStock1Test extends TestCase
{
    private const EU_STOCK = 10;
    private const NEW_WEBSITE = 'without_stock_1';

    /**
     * @var AdapterInterface
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    public function testANewWebsiteIsCreatedWithoutAStock(): void
    {
        $this->runWithoutStock1(function (): void {
            $website = Bootstrap::getObjectManager()->get(WebsiteFactory::class)->create();
            $website->setCode(self::NEW_WEBSITE)->setName('Without stock 1');
            $website->save();

            self::assertNotNull($website->getId());
            self::assertNull($this->getStockIdOfWebsite(self::NEW_WEBSITE));
        });
    }

    public function testSavingAStockThatDropsAWebsiteLeavesTheWebsiteWithoutAStock(): void
    {
        $this->runWithoutStock1(function (): void {
            try {
                $this->saveStockWithWebsites(self::EU_STOCK, ['base']);
            } catch (CouldNotSaveException $exception) {
                self::fail($exception->getMessage() . ': ' . $exception->getPrevious()?->getMessage());
            }

            self::assertSame(self::EU_STOCK, $this->getStockIdOfWebsite('base'));
            self::assertNull($this->getStockIdOfWebsite('eu_website'));
        });
    }

    private function runWithoutStock1(callable $test): void
    {
        $stockId = $this->getDefaultStockId();
        $stock1 = $this->connection->fetchRow(
            'SELECT * FROM ' . $this->connection->getTableName('inventory_stock') . ' WHERE stock_id = ?',
            [$stockId]
        );
        self::assertNotEmpty($stock1, 'Stock 1 must exist before the test deletes it.');
        $stock1Links = $this->connection->fetchAll(
            'SELECT * FROM ' . $this->connection->getTableName('inventory_source_stock_link')
            . ' WHERE stock_id = ?',
            [$stockId]
        );

        try {
            $this->replaceWebsites(self::EU_STOCK, ['eu_website', 'base']);
            $this->startNewRequest();
            Bootstrap::getObjectManager()->get(StockRepositoryInterface::class)->deleteById($stockId);
            $this->startNewRequest();
            $test();
        } finally {
            $this->connection->insertOnDuplicate($this->connection->getTableName('inventory_stock'), $stock1);
            foreach ($stock1Links as $link) {
                $this->connection->insertOnDuplicate(
                    $this->connection->getTableName('inventory_source_stock_link'),
                    $link
                );
            }
            $this->replaceWebsites(self::EU_STOCK, ['eu_website']);
            $this->replaceWebsites($stockId, ['base']);
            $this->deleteNewWebsite();
        }
    }

    private function saveStockWithWebsites(int $stockId, array $websiteCodes): void
    {
        $this->startNewRequest();
        $repository = Bootstrap::getObjectManager()->get(StockRepositoryInterface::class);
        $stock = $repository->get($stockId);
        $stock->getExtensionAttributes()->setSalesChannels($this->createWebsiteChannels($websiteCodes));
        $repository->save($stock);
    }

    private function replaceWebsites(int $stockId, array $websiteCodes): void
    {
        $this->startNewRequest();
        Bootstrap::getObjectManager()->get(ReplaceSalesChannelsForStockInterface::class)
            ->execute($this->createWebsiteChannels($websiteCodes), $stockId);
    }

    private function deleteNewWebsite(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $website = $objectManager->get(WebsiteFactory::class)->create()->load(self::NEW_WEBSITE, 'code');
        if (!$website->getId()) {
            return;
        }
        $registry = $objectManager->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        $website->delete();
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', false);
    }

    private function startNewRequest(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $caches = [
            GetStockInterface::class,
            GetStockBySalesChannelInterface::class,
            GetAssignedStockIdForWebsiteInterface::class,
        ];
        foreach ($caches as $type) {
            $cache = $objectManager->get($type);
            if ($cache instanceof ResetAfterRequestInterface) {
                $cache->_resetState();
            }
        }
    }

    private function createWebsiteChannels(array $websiteCodes): array
    {
        $factory = Bootstrap::getObjectManager()->get(SalesChannelInterfaceFactory::class);

        return array_map(
            fn (string $code) => $factory->create(['data' => [
                SalesChannelInterface::CODE => $code,
                SalesChannelInterface::TYPE => SalesChannelInterface::TYPE_WEBSITE,
            ]]),
            $websiteCodes
        );
    }

    private function getDefaultStockId(): int
    {
        return (int)Bootstrap::getObjectManager()->get(DefaultStockProviderInterface::class)->getId();
    }

    private function getStockIdOfWebsite(string $websiteCode): ?int
    {
        $stockId = $this->connection->fetchOne(
            'SELECT stock_id FROM ' . $this->connection->getTableName('inventory_stock_sales_channel')
            . ' WHERE type = ? AND code = ?',
            [SalesChannelInterface::TYPE_WEBSITE, $websiteCode]
        );

        return $stockId === false ? null : (int)$stockId;
    }
}
