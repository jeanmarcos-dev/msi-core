<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Test\Unit\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\InventoryAdjustmentAdminUi\Ui\DataProvider\ProductAdjustmentDataProvider;
use PHPUnit\Framework\TestCase;

class ProductAdjustmentDataProviderTest extends TestCase
{
    /**
     * @var array
     */
    private array $filters = [];

    public function testTheProductSkuFiltersTheRowsAndTravelsWithEveryReload(): void
    {
        $provider = $this->provider('SKU/1 A');

        self::assertSame([['sku', 'SKU/1 A', 'eq']], $this->filters);
        self::assertSame('SKU/1 A', $provider->getConfigData()['params']['sku']);
    }

    public function testWithoutASkuNoRowMatches(): void
    {
        $this->provider(null);

        self::assertSame([['sku', '', 'eq']], $this->filters);
    }

    private function provider(?string $sku): ProductAdjustmentDataProvider
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['sku', null, $sku]]);
        $filterBuilder = $this->createMock(FilterBuilder::class);
        $field = $value = $condition = null;
        $filterBuilder->method('setField')->willReturnCallback(function ($given) use (&$field, $filterBuilder) {
            $field = $given;
            return $filterBuilder;
        });
        $filterBuilder->method('setValue')->willReturnCallback(function ($given) use (&$value, $filterBuilder) {
            $value = $given;
            return $filterBuilder;
        });
        $filterBuilder->method('setConditionType')->willReturnCallback(
            function ($given) use (&$condition, $filterBuilder) {
                $condition = $given;
                return $filterBuilder;
            }
        );
        $filterBuilder->method('create')->willReturnCallback(function () use (&$field, &$value, &$condition) {
            $this->filters[] = [$field, $value, $condition];
            return new Filter();
        });

        return new ProductAdjustmentDataProvider(
            'inventory_adjustment_product_listing_data_source',
            'adjustment_id',
            'id',
            $this->createMock(ReportingInterface::class),
            $this->createMock(SearchCriteriaBuilder::class),
            $request,
            $filterBuilder,
            [],
            ['config' => ['update_url' => 'https://admin/mui/index/render/']]
        );
    }
}
