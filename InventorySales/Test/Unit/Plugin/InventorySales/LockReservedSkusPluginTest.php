<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventorySales;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Model\ReservationLock;
use Magento\InventorySales\Plugin\InventorySales\LockReservedSkusPlugin;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LockReservedSkusPluginTest extends TestCase
{
    private const STOCK_ID = 5;

    /**
     * @var string[]
     */
    private array $calls = [];

    /**
     * @var ReservationLock|MockObject
     */
    private $reservationLock;

    /**
     * @var LockReservedSkusPlugin
     */
    private $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->reservationLock = $this->createMock(ReservationLock::class);
        $this->reservationLock->method('release')->willReturnCallback(function (array $names) {
            $this->calls[] = 'release:' . implode(',', $names);
        });
        $stock = $this->createConfiguredMock(StockInterface::class, ['getStockId' => self::STOCK_ID]);
        $stockResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockResolver->method('execute')->with(1)->willReturn($stock);
        $this->plugin = new LockReservedSkusPlugin($this->reservationLock, $stockResolver);
    }

    public function testLocksBeforeReservingAndReleasesAfter(): void
    {
        $this->reservationLock->expects($this->once())->method('acquire')->with(['lk_s', 'lk_free'], self::STOCK_ID)
            ->willReturnCallback(function () {
                $this->calls[] = 'acquire';
                return ['lock_lk_s'];
            });
        $proceed = function ($websiteId, $itemsBySku, $order, $itemsToSell) {
            $this->calls[] = 'reserve';
            return ['channel', 'extension'];
        };

        $result = $this->aroundReserve($proceed, ['lk_s' => -1.0, 'lk_free' => -1.0]);

        $this->assertSame(['channel', 'extension'], $result);
        $this->assertSame(['acquire', 'reserve', 'release:lock_lk_s'], $this->calls);
    }

    public function testPassesTheArgumentsThrough(): void
    {
        $this->reservationLock->method('acquire')->willReturn([]);
        $order = new \stdClass();
        $proceed = function ($websiteId, $itemsBySku, $receivedOrder, $itemsToSell) use ($order, &$items) {
            $this->assertSame(1, $websiteId);
            $this->assertSame(['lk_s' => 1.0], $itemsBySku);
            $this->assertSame($order, $receivedOrder);
            $this->assertSame($items, $itemsToSell);
            return [];
        };
        $items = $this->itemsToSell(['lk_s' => -1.0]);

        $this->plugin->aroundReserve(
            $this->createMock(AppendReservations::class),
            $proceed,
            1,
            ['lk_s' => 1.0],
            $order,
            $items
        );
    }

    public function testReleasesWhenTheReservationFails(): void
    {
        $this->reservationLock->method('acquire')->willReturn(['lock_lk_s']);
        $failure = new LocalizedException(__('Not enough items for sale'));
        $proceed = static function () use ($failure) {
            throw $failure;
        };

        try {
            $this->aroundReserve($proceed, ['lk_s' => -1.0]);
            $this->fail('expected the reservation to fail');
        } catch (LocalizedException $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame(['release:lock_lk_s'], $this->calls);
    }

    public function testLocksExactlyTheSkusItIsAskedToReserve(): void
    {
        $this->reservationLock->expects($this->once())->method('acquire')
            ->with(['lk_s', '123'], self::STOCK_ID)
            ->willReturn(['lock_lk_s', 'lock_123']);

        $this->aroundReserve(static fn() => [], ['lk_s' => -1.0, '123' => -2.0]);

        $this->assertSame(['release:lock_lk_s,lock_123'], $this->calls);
    }

    public function testTakesNoLockForARestockingReservation(): void
    {
        $this->reservationLock->expects($this->never())->method('acquire');
        $reserved = false;

        $this->aroundReserve(function () use (&$reserved) {
            $reserved = true;
            return [];
        }, ['lk_s' => 1.0]);

        $this->assertTrue($reserved);
    }

    public function testLocksOnlyTheSkusWithNetDemand(): void
    {
        $this->reservationLock->expects($this->once())->method('acquire')->with(['lk_s'], self::STOCK_ID)
            ->willReturn(['lock_lk_s']);

        $this->aroundReserve(static fn() => [], ['lk_s' => -2.0, 'lk_back' => 1.0]);
    }

    /**
     * @param callable $proceed
     * @param array<string,float> $quantityBySku
     * @return mixed
     */
    private function aroundReserve(callable $proceed, array $quantityBySku)
    {
        return $this->plugin->aroundReserve(
            $this->createMock(AppendReservations::class),
            $proceed,
            1,
            array_map('abs', $quantityBySku),
            new \stdClass(),
            $this->itemsToSell($quantityBySku)
        );
    }

    /**
     * @param array<string,float> $quantityBySku
     * @return ItemToSellInterface[]
     */
    private function itemsToSell(array $quantityBySku): array
    {
        $items = [];
        foreach ($quantityBySku as $sku => $quantity) {
            $items[] = $this->createConfiguredMock(
                ItemToSellInterface::class,
                ['getSku' => (string)$sku, 'getQuantity' => $quantity]
            );
        }

        return $items;
    }
}
