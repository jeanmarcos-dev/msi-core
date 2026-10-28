<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySourceDeductionApi\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationExtensionInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Model\InventoryConfigurationInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SourceDeductionServiceTest extends TestCase
{
    use MockCreationTrait;

    private const IN = SourceItemInterface::STATUS_IN_STOCK;
    private const OUT = SourceItemInterface::STATUS_OUT_OF_STOCK;

    /**
     * @var GetSourceItemBySourceCodeAndSku|Stub
     */
    private $getSourceItemBySourceCodeAndSku;

    /**
     * @var GetStockItemConfigurationInterface|Stub
     */
    private $getStockItemConfiguration;

    /**
     * @var DecrementSourceItemQty|MockObject
     */
    private $decrementSourceItem;

    /**
     * @var InventoryConfigurationInterface|Stub
     */
    private $inventoryConfiguration;

    /**
     * @var SourceDeductionService
     */
    private $model;

    protected function setUp(): void
    {
        $this->getSourceItemBySourceCodeAndSku = $this->createStub(GetSourceItemBySourceCodeAndSku::class);
        $this->getStockItemConfiguration = $this->createStub(GetStockItemConfigurationInterface::class);
        $this->decrementSourceItem = $this->createMock(DecrementSourceItemQty::class);
        $this->inventoryConfiguration = $this->createStub(InventoryConfigurationInterface::class);
        $stock = $this->createStub(StockInterface::class);
        $stock->method('getStockId')->willReturn(1);
        $getStockBySalesChannel = $this->createStub(GetStockBySalesChannelInterface::class);
        $getStockBySalesChannel->method('execute')->willReturn($stock);

        $this->model = new SourceDeductionService(
            $this->getSourceItemBySourceCodeAndSku,
            $this->getStockItemConfiguration,
            $getStockBySalesChannel,
            $this->decrementSourceItem,
            $this->inventoryConfiguration
        );
    }

    /**
     * @param float $qty
     * @param float $sourceQty
     * @param int $sourceStatus
     * @param float $minQty
     * @param int $backorders
     * @param bool $canBackInStock
     * @param int|null $expectedStatus
     * @return void
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    #[DataProvider('statusCases')]
    public function testTheDecrementCarriesAStatusOnlyWhenTheSourceChangesState(
        float $qty,
        float $sourceQty,
        int $sourceStatus,
        float $minQty,
        int $backorders,
        bool $canBackInStock,
        ?int $expectedStatus
    ): void {
        $this->inventoryConfiguration->method('isCanBackInStock')->willReturn($canBackInStock);
        $this->getStockItemConfiguration->method('execute')->willReturn($this->configuration($minQty, $backorders));
        $this->getSourceItemBySourceCodeAndSku->method('execute')
            ->willReturn($this->sourceItem($sourceQty, $sourceStatus, $sourceQty - $qty));
        $this->expectDecrement($qty, $expectedStatus);

        $this->model->execute($this->request($qty));
    }

    /**
     * @return array[]
     */
    public static function statusCases(): array
    {
        return [
            'depleting without backorders sends out of stock' => [2.0, 2.0, self::IN, 0.0, 0, true, self::OUT],
            'depleting with backorders sends nothing' => [2.0, 2.0, self::IN, 0.0, 1, true, null],
            'staying above the threshold sends nothing' => [2.0, 5.0, self::IN, 0.0, 0, true, null],
            'a manual out of stock above the threshold is kept' => [1.0, 10.0, self::OUT, 0.0, 0, true, null],
            'crossing a threshold of 2 sends out of stock' => [2.0, 3.0, self::IN, 2.0, 0, true, self::OUT],
            'a return to an out of stock source sends in stock' => [-1.0, 0.0, self::OUT, 0.0, 0, true, self::IN],
            'a return sends nothing when items cannot be back in stock' => [-1.0, 0.0, self::OUT, 0.0, 0, false, null],
            'a return that stays at the threshold sends nothing' => [-2.0, 0.0, self::OUT, 2.0, 0, true, null],
            'a return to an in stock source sends nothing' => [-1.0, 3.0, self::IN, 0.0, 0, true, null],
        ];
    }

    /**
     * @param int $sourceStatus
     * @param float $sourceQty
     * @param int|null $expectedStatus
     * @return void
     */
    #[DataProvider('productStatusCases')]
    public function testThePreviousStatusIsTheSourceItemsAndNotTheProducts(
        int $sourceStatus,
        float $sourceQty,
        ?int $expectedStatus
    ): void {
        $this->inventoryConfiguration->method('isCanBackInStock')->willReturn(true);
        $configuration = $this->configuration(0.0, 0);
        $productStatus = $this->createPartialMockWithReflection(
            StockItemConfigurationExtensionInterface::class,
            ['getIsInStock', 'setIsInStock']
        );
        $productStatus->expects(self::never())->method('getIsInStock');
        $configuration->method('getExtensionAttributes')->willReturn($productStatus);
        $this->getStockItemConfiguration->method('execute')->willReturn($configuration);
        $this->getSourceItemBySourceCodeAndSku->method('execute')
            ->willReturn($this->sourceItem($sourceQty, $sourceStatus, $sourceQty + 1));
        $this->expectDecrement(-1.0, $expectedStatus);

        $this->model->execute($this->request(-1.0));
    }

    /**
     * @return array[]
     */
    public static function productStatusCases(): array
    {
        return [
            'an in stock source stays as it is' => [self::IN, 3.0, null],
            'an out of stock source comes back' => [self::OUT, 0.0, self::IN],
        ];
    }

    private function expectDecrement(float $qty, ?int $expectedStatus): void
    {
        $this->decrementSourceItem->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $decrementItems) use ($qty, $expectedStatus): bool {
                self::assertCount(1, $decrementItems);
                self::assertSame($qty, $decrementItems[0]['qty_to_decrement']);
                self::assertSame($expectedStatus, $decrementItems[0]['status'] ?? null);

                return true;
            }));
    }

    private function configuration(float $minQty, int $backorders): StockItemConfigurationInterface&Stub
    {
        $configuration = $this->createStub(StockItemConfigurationInterface::class);
        $configuration->method('isManageStock')->willReturn(true);
        $configuration->method('getMinQty')->willReturn($minQty);
        $configuration->method('getBackorders')->willReturn($backorders);

        return $configuration;
    }

    private function sourceItem(float $quantity, int $status, float $quantityAfter): SourceItemInterface&MockObject
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->method('getQuantity')->willReturn($quantity);
        $sourceItem->method('getStatus')->willReturn($status);
        $sourceItem->expects(self::once())->method('setQuantity')->with($quantityAfter);

        return $sourceItem;
    }

    private function request(float $qty): SourceDeductionRequestInterface&Stub
    {
        $item = $this->createStub(ItemToDeductInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getQty')->willReturn($qty);
        $request = $this->createStub(SourceDeductionRequestInterface::class);
        $request->method('getSourceCode')->willReturn('sri_a');
        $request->method('getSalesChannel')->willReturn($this->createStub(SalesChannelInterface::class));
        $request->method('getItems')->willReturn([$item]);

        return $request;
    }
}
