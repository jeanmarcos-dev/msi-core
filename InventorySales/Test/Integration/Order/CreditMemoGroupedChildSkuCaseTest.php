<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Integration\Order;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\GroupedProduct\Test\Fixture\AddProductToCart as AddGroupedProductToCartFixture;
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Test\Fixture\SourceItem as SourceItemFixture;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Test\Fixture\Invoice as InvoiceFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true)]
class CreditMemoGroupedChildSkuCaseTest extends TestCase
{
    private const CHILD_SKU = 'refund-grouped-child';

    public static function catalogSkus(): array
    {
        return [
            'the catalog keeps the ordered sku' => [self::CHILD_SKU],
            'the catalog sku changed case after the order' => [strtoupper(self::CHILD_SKU)],
        ];
    }

    #[
        DataProvider('catalogSkus'),
        DataFixture(ProductFixture::class, ['sku' => self::CHILD_SKU], 'child'),
        DataFixture(SourceItemFixture::class, ['sku' => '$child.sku$', 'source_code' => 'default', 'quantity' => 100]),
        DataFixture(GroupedProductFixture::class, ['product_links' => ['$child$']], 'parent'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(
            AddGroupedProductToCartFixture::class,
            [
                'cart_id' => '$cart.id$',
                'product_id' => '$parent.id$',
                'child_products' => [['product_id' => '$child.id$', 'qty' => 2]],
            ]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order'),
        DataFixture(InvoiceFixture::class, ['order_id' => '$order.id$']),
    ]
    public function testRefundWithoutReturnToStockDeductsTheGroupedChild(string $catalogSku): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $stockId = $objectManager->get(DefaultStockProviderInterface::class)->getId();
        $reservations = $objectManager->get(GetReservationsQuantityInterface::class);
        self::assertSame(-2.0, $reservations->execute(self::CHILD_SKU, $stockId));

        $connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $connection->update(
            $connection->getTableName('catalog_product_entity'),
            ['sku' => $catalogSku],
            ['entity_id = ?' => (int)$fixtures->get('child')->getId()]
        );

        $creditMemo = $objectManager->create(CreditmemoFactory::class)->createByOrder($fixtures->get('order'));
        $objectManager->create(CreditmemoRepositoryInterface::class)->save($creditMemo);

        $sourceItems = $objectManager->get(GetSourceItemsBySkuInterface::class)->execute(self::CHILD_SKU);
        self::assertSame(98.0, (float)current($sourceItems)->getQuantity());
        self::assertSame(0.0, $reservations->execute(self::CHILD_SKU, $stockId));
    }
}
