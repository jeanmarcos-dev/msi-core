<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\InventoryApi\Model\IsProductAssignedToStockInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfiguration\Model\GetNonBackorderableProductTypes;
use Magento\InventoryConfiguration\Model\GetStockItemConfiguration;
use Magento\InventoryConfiguration\Model\GetStockItemConfigurationBySku;
use Magento\InventoryConfiguration\Model\NonBackorderableStockItemConfiguration;
use Magento\InventoryConfiguration\Model\NonBackorderableStockItemConfigurationFactory;
use Magento\InventoryConfiguration\Model\StockItemConfiguration;
use Magento\InventoryConfiguration\Model\StockItemConfigurationFactory;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetStockItemConfigurationTest extends TestCase
{
    private const BACKORDERS_YES = StockItemConfigurationInterface::BACKORDERS_YES_NONOTIFY;
    private const BACKORDERS_NO = StockItemConfigurationInterface::BACKORDERS_NO;
    private const NON_BACKORDERABLE = ['virtual' => 'virtual', 'downloadable' => 'downloadable'];

    /**
     * @param string|null $type
     * @param bool $useConfig
     * @param int $backorders
     * @param float $minQty
     * @param int $expectedBackorders
     * @param float $expectedMinQty
     * @return void
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    #[DataProvider('products')]
    public function testTheEffectiveBackordersDependOnTheProductType(
        ?string $type,
        bool $useConfig,
        int $backorders,
        float $minQty,
        int $expectedBackorders,
        float $expectedMinQty
    ): void {
        $configuration = $this->getStockItemConfiguration(
            self::NON_BACKORDERABLE,
            $type,
            $useConfig,
            $backorders,
            $minQty
        )->execute('SKU-1', 1);

        self::assertSame($expectedBackorders, $configuration->getBackorders());
        self::assertSame($expectedMinQty, $configuration->getMinQty());
    }

    /**
     * @return array[]
     */
    public static function products(): array
    {
        return [
            'a virtual product with its own backorders' => [
                'virtual', false, self::BACKORDERS_YES, -5.0, self::BACKORDERS_NO, 0.0,
            ],
            'a virtual product on the global backorders setting' => [
                'virtual', true, self::BACKORDERS_YES, -5.0, self::BACKORDERS_NO, 0.0,
            ],
            'a downloadable product with its own backorders' => [
                'downloadable', false, self::BACKORDERS_YES, -5.0, self::BACKORDERS_NO, 0.0,
            ],
            'a downloadable product on the global backorders setting' => [
                'downloadable', true, self::BACKORDERS_YES, -5.0, self::BACKORDERS_NO, 0.0,
            ],
            'a virtual product keeps a threshold above zero' => [
                'virtual', false, self::BACKORDERS_NO, 3.0, self::BACKORDERS_NO, 3.0,
            ],
            'a simple product keeps its backorders' => [
                'simple', false, self::BACKORDERS_YES, -5.0, self::BACKORDERS_YES, -5.0,
            ],
            'a product missing from the catalog keeps its backorders' => [
                null, false, self::BACKORDERS_YES, -5.0, self::BACKORDERS_YES, -5.0,
            ],
        ];
    }

    public function testAnEmptyListOfTypesLeavesVirtualProductsAsConfigured(): void
    {
        $configuration = $this->getStockItemConfiguration([], 'virtual', false, self::BACKORDERS_YES, -5.0)
            ->execute('SKU-1', 1);

        self::assertSame(self::BACKORDERS_YES, $configuration->getBackorders());
        self::assertSame(-5.0, $configuration->getMinQty());
    }

    private function getStockItemConfiguration(
        array $nonBackorderableTypes,
        ?string $type,
        bool $useConfig,
        int $backorders,
        float $minQty
    ): GetStockItemConfiguration {
        $stockItem = $this->createStub(StockItemInterface::class);
        $stockItem->method('getUseConfigBackorders')->willReturn($useConfig);
        $stockItem->method('getUseConfigMinQty')->willReturn($useConfig);
        $stockItem->method('getBackorders')->willReturn($backorders);
        $stockItem->method('getMinQty')->willReturn($minQty);
        $getStockItemConfigurationBySku = $this->createStub(GetStockItemConfigurationBySku::class);
        $getStockItemConfigurationBySku->method('execute')->willReturn($stockItem);

        $configurationFactory = $this->createStub(StockItemConfigurationFactory::class);
        $configurationFactory->method('create')
            ->willReturnCallback(fn (array $data) => $this->configuration(StockItemConfiguration::class, $data));
        $nonBackorderableFactory = $this->createStub(NonBackorderableStockItemConfigurationFactory::class);
        $nonBackorderableFactory->method('create')
            ->willReturnCallback(fn (array $data) => $this->configuration(
                NonBackorderableStockItemConfiguration::class,
                $data
            ));

        $getProductTypesBySkus = $this->createStub(GetProductTypesBySkusInterface::class);
        $getProductTypesBySkus->method('execute')->willReturn($type === null ? [] : ['SKU-1' => $type]);
        $defaultStockProvider = $this->createStub(DefaultStockProviderInterface::class);
        $defaultStockProvider->method('getId')->willReturn(1);

        return new GetStockItemConfiguration(
            $getStockItemConfigurationBySku,
            $configurationFactory,
            $this->createStub(IsProductAssignedToStockInterface::class),
            $defaultStockProvider,
            $this->createStub(IsSourceItemManagementAllowedForSkuInterface::class),
            $getProductTypesBySkus,
            new GetNonBackorderableProductTypes($nonBackorderableTypes),
            $nonBackorderableFactory
        );
    }

    private function configuration(string $class, array $data): StockItemConfigurationInterface
    {
        return new $class(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            $this->createStub(ExtensionAttributesFactory::class),
            $this->createStub(AttributeValueFactory::class),
            $data['stockItem'],
            $this->createStub(ScopeConfigInterface::class)
        );
    }
}
