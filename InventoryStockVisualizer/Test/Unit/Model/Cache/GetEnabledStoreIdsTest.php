<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\InventoryStockVisualizer\Model\Cache\GetEnabledStoreIds;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetEnabledStoreIdsTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var GetEnabledStoreIds
     */
    private $model;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStores')->willReturn([$this->store(1), $this->store(2)]);
        $this->model = new GetEnabledStoreIds($this->config, $this->storeManager);
    }

    public function testEveryStoreThatInheritsAnEnabledDefaultIsReturned(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->assertSame([1, 2], $this->model->execute());
    }

    public function testOnlyTheStoreViewThatEnablesThePanelIsReturned(): void
    {
        $this->config->method('isEnabled')->willReturnCallback(fn($store = null) => $store === 2);

        $this->assertSame([2], $this->model->execute());
    }

    public function testNothingIsReturnedWhenNoStoreEnablesThePanel(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->assertSame([], $this->model->execute());
    }

    public function testTheAdminStoreIsLeftOut(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())->method('getStores')->with(false)->willReturn([$this->store(1)]);
        $this->config->method('isEnabled')->willReturn(true);

        $this->assertSame([1], (new GetEnabledStoreIds($this->config, $storeManager))->execute());
    }

    public function testASecondCallDoesNotReadTheConfigurationAgain(): void
    {
        $this->config->expects($this->exactly(2))->method('isEnabled')->willReturn(true);

        $this->model->execute();
        $this->assertSame([1, 2], $this->model->execute());
    }

    public function testTheResetForgetsTheStores(): void
    {
        $this->config->expects($this->exactly(4))->method('isEnabled')->willReturn(true);

        $this->model->execute();
        $this->model->_resetState();
        $this->model->execute();
    }

    /**
     * @param int $storeId
     * @return StoreInterface
     */
    private function store(int $storeId): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }
}
