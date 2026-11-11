<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Plugin\GraphQl;

use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchRequestItemInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use Magento\InventoryConfigurableProduct\Plugin\GraphQl\ConfigurableBatchStockStatusPlugin;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurableBatchStockStatusPluginTest extends TestCase
{
    private const STORE_ID = 5;

    /**
     * @var SalableChildren|MockObject
     */
    private $salableChildren;

    /**
     * @var ConfigurableBatchStockStatusPlugin
     */
    private ConfigurableBatchStockStatusPlugin $plugin;

    protected function setUp(): void
    {
        $this->salableChildren = $this->createMock(SalableChildren::class);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->plugin = new ConfigurableBatchStockStatusPlugin($storeManager, $this->salableChildren);
    }

    public function testConfigurableWithoutSalableChildrenInTheStoreIsOutOfStock(): void
    {
        $withChildren = $this->createProduct('configurable');
        $withoutChildren = $this->createProduct('configurable');
        $requests = [
            $this->createRequest(['model' => $withChildren]),
            $this->createRequest(['model' => $withoutChildren]),
        ];
        $this->salableChildren->method('exist')->willReturnCallback(
            fn (Product $product, int $storeId) => $storeId === self::STORE_ID && $product === $withChildren
        );

        $response = $this->afterResolve($requests, ['IN_STOCK', 'IN_STOCK']);

        self::assertSame('IN_STOCK', $response->findResponseFor($requests[0]));
        self::assertSame('OUT_OF_STOCK', $response->findResponseFor($requests[1]));
    }

    public function testChildrenOfAllConfigurablesInStockAreLoadedTogether(): void
    {
        $first = $this->createProduct('configurable');
        $second = $this->createProduct('configurable');
        $outOfStock = $this->createProduct('configurable');
        $requests = [
            $this->createRequest(['model' => $first]),
            $this->createRequest(['model' => $this->createProduct('simple')]),
            $this->createRequest(['model' => $outOfStock]),
            $this->createRequest(['model' => $second]),
        ];
        $this->salableChildren->expects(self::once())
            ->method('preload')
            ->with([$first, $second], self::STORE_ID);
        $this->salableChildren->method('exist')->willReturn(true);

        $this->afterResolve($requests, ['IN_STOCK', 'IN_STOCK', 'OUT_OF_STOCK', 'IN_STOCK']);
    }

    public function testOtherResponsesAreLeftAlone(): void
    {
        $error = new \stdClass();
        $requests = [
            $this->createRequest(['model' => $this->createProduct('simple')]),
            $this->createRequest(['model' => $this->createProduct('configurable')]),
            $this->createRequest(['model' => $this->createProduct('configurable')]),
            $this->createRequest(['sku' => 'x']),
            $this->createRequest(null),
        ];
        $this->salableChildren->expects(self::never())->method('preload');
        $this->salableChildren->expects(self::never())->method('exist');

        $response = $this->afterResolve($requests, ['IN_STOCK', 'OUT_OF_STOCK', $error, 'IN_STOCK', 'IN_STOCK']);

        self::assertSame('IN_STOCK', $response->findResponseFor($requests[0]));
        self::assertSame('OUT_OF_STOCK', $response->findResponseFor($requests[1]));
        self::assertSame($error, $response->findResponseFor($requests[2]));
        self::assertSame('IN_STOCK', $response->findResponseFor($requests[3]));
        self::assertSame('IN_STOCK', $response->findResponseFor($requests[4]));
    }

    private function afterResolve(array $requests, array $resolved): BatchResponse
    {
        $response = new BatchResponse();
        foreach ($requests as $key => $request) {
            $response->addResponse($request, $resolved[$key]);
        }

        return $this->plugin->afterResolve(
            $this->createStub(BatchResolverInterface::class),
            $response,
            $this->createStub(ContextInterface::class),
            $this->createStub(Field::class),
            $requests
        );
    }

    private function createRequest(?array $value): BatchRequestItemInterface
    {
        $request = $this->createStub(BatchRequestItemInterface::class);
        $request->method('getValue')->willReturn($value);

        return $request;
    }

    private function createProduct(string $typeId): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn($typeId);

        return $product;
    }
}
