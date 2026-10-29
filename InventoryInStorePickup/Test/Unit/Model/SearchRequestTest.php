<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryInStorePickup\Test\Unit\Model;

use Magento\Framework\Api\SortOrder;
use Magento\InventoryInStorePickup\Model\SearchRequest;
use Magento\InventoryInStorePickupApi\Api\Data\SearchRequestInterface;
use PHPUnit\Framework\TestCase;

class SearchRequestTest extends TestCase
{
    public function testSortIsNullByDefault(): void
    {
        $this->assertNull((new SearchRequest('base'))->getSort());
    }

    public function testSetSortReplacesTheSortOrders(): void
    {
        $sortOrder = new SortOrder();
        $model = new SearchRequest('base');

        $model->setSort([$sortOrder]);

        $this->assertSame([$sortOrder], $model->getSort());
    }

    public function testSetSortAcceptsNull(): void
    {
        $model = new SearchRequest('base', 'website', null, null, [new SortOrder()]);

        $model->setSort(null);

        $this->assertNull($model->getSort());
    }

    public function testInterfaceDeclaresSetSort(): void
    {
        $this->assertTrue((new \ReflectionClass(SearchRequestInterface::class))->hasMethod('setSort'));
    }
}
