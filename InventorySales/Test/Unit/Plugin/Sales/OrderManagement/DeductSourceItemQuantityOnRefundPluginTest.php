<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\Sales\OrderManagement;

use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySales\Model\ReturnProcessor\DeductSourceItemQuantityOnRefund;
use Magento\InventorySales\Model\ReturnProcessor\Request\ItemsToRefund;
use Magento\InventorySales\Plugin\Sales\OrderManagement\DeductSourceItemQuantityOnRefundPlugin;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\InventorySalesApi\Model\ReturnProcessor\Request\ItemsToRefundInterfaceFactory;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeductSourceItemQuantityOnRefundPluginTest extends TestCase
{
    private const SKU = 'refund-grouped-child';

    public function testDeductsAnItemWhoseProductExists(): void
    {
        $deductions = $this->refund([self::SKU => 'simple']);

        $this->assertCount(1, $deductions);
        [$items, $orderItemIds] = $deductions[0];
        $this->assertSame([7], $orderItemIds);
        $this->assertCount(1, $items);
        $this->assertSame(self::SKU, $items[0]->getSku());
        $this->assertSame(2.0, $items[0]->getQuantity());
    }

    #[DataProvider('missingProducts')]
    public function testSkipsAnItemWhoseProductNoLongerExists(array $typesBySkus): void
    {
        $this->assertSame([], $this->refund($typesBySkus));
    }

    public static function missingProducts(): array
    {
        return [
            'left out of the type lookup' => [[]],
            'without a type in the cached type lookup' => [[self::SKU => null]],
        ];
    }

    private function refund(array $typesBySkus): array
    {
        $orderItem = $this->createPartialMock(OrderItem::class, []);
        $orderItem->setData(['product_type' => 'grouped', 'is_virtual' => 0, 'qty_invoiced' => 2, 'qty_refunded' => 2]);

        $item = $this->createPartialMock(CreditmemoItem::class, ['getOrderItem']);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->setData(['order_item_id' => 7, 'qty' => 2, 'back_to_stock' => false]);

        $creditMemo = $this->createMock(CreditmemoInterface::class);
        $creditMemo->method('getEntityId')->willReturn(null);
        $creditMemo->method('getOrderId')->willReturn(3);
        $creditMemo->method('getItems')->willReturn([$item]);

        $order = $this->createMock(OrderInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->with(3)->willReturn($order);

        $getSkuFromOrderItem = $this->createMock(GetSkuFromOrderItemInterface::class);
        $getSkuFromOrderItem->method('execute')->with($orderItem)->willReturn(self::SKU);

        $getProductTypesBySkus = $this->createMock(GetProductTypesBySkusInterface::class);
        $getProductTypesBySkus->method('execute')->with([self::SKU])->willReturn($typesBySkus);

        $isSourceItemManagementAllowed = $this->createMock(IsSourceItemManagementAllowedForProductTypeInterface::class);
        $isSourceItemManagementAllowed->method('execute')->with('simple')->willReturn(true);

        $itemsToRefundFactory = $this->createMock(ItemsToRefundInterfaceFactory::class);
        $itemsToRefundFactory->method('create')->willReturnCallback(
            static fn (array $data) => new ItemsToRefund(...$data)
        );

        $deductions = [];
        $deduct = $this->createMock(DeductSourceItemQuantityOnRefund::class);
        $deduct->method('execute')->willReturnCallback(
            function (OrderInterface $deductedOrder, array $items, array $orderItemIds) use ($order, &$deductions) {
                $this->assertSame($order, $deductedOrder);
                $deductions[] = [$items, $orderItemIds];
            }
        );

        $plugin = new DeductSourceItemQuantityOnRefundPlugin(
            $getSkuFromOrderItem,
            $itemsToRefundFactory,
            $isSourceItemManagementAllowed,
            $getProductTypesBySkus,
            $deduct,
            $orderRepository
        );
        $plugin->aroundSave(
            $this->createMock(CreditmemoRepositoryInterface::class),
            static fn (CreditmemoInterface $entity) => $entity,
            $creditMemo
        );

        return $deductions;
    }
}
