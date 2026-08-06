<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryShippingAdminUi\Test\Integration\Model;

use Magento\InventoryCatalog\Model\DeleteSourceItemsBySkus;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\SaveStockItemConfigurationInterface;
use Magento\InventoryShippingAdminUi\Model\IsOrderSourceManageable;
use Magento\Sales\Model\OrderFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verify IsOrderSourceManageable service.
 */
class IsOrderSourceManageableTest extends TestCase
{
    private const DEFAULT_STOCK_ID = 1;

    /**
     * Test subject.
     *
     * @var IsOrderSourceManageable
     */
    private $isOrderSourceManageable;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->isOrderSourceManageable = Bootstrap::getObjectManager()->get(IsOrderSourceManageable::class);
        $this->disableManageStock();
        $this->cleanUpSourceItems();
    }

    /**
     * Verify order with not assigned to all stocks items won't throw exception.
     *
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testOrderWithProductsNotAssignedToStocks(): void
    {
        $order = Bootstrap::getObjectManager()->get(OrderFactory::class)->create()->loadByIncrementId('100000001');
        $result = $this->isOrderSourceManageable->execute($order);
        self::assertFalse($result);
    }

    /**
     * Clean up source items for test product as they may be left by other tests run.
     *
     * @return void
     */
    private function cleanUpSourceItems(): void
    {
        $deleteSourceItems = Bootstrap::getObjectManager()->get(DeleteSourceItemsBySkus::class);
        $deleteSourceItems->execute(['simple']);
    }

    /**
     * Disable manage stock for default stock item.
     *
     * @return void
     */
    private function disableManageStock(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $configuration = $objectManager->get(GetStockItemConfigurationInterface::class)
            ->execute('simple', self::DEFAULT_STOCK_ID);
        $configuration->setManageStock(false);
        $configuration->setUseConfigManageStock(false);
        $objectManager->get(SaveStockItemConfigurationInterface::class)
            ->execute('simple', self::DEFAULT_STOCK_ID, $configuration);
    }
}
