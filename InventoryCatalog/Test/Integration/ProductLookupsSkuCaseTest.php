<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\InventoryCatalog\Model\GetProductIdsBySkus;
use Magento\InventoryCatalog\Model\ResourceModel\GetProductTypesBySkus;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ProductLookupsSkuCaseTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'case-simple'], 'product')]
    public function testFindsTheIdOfASkuWhoseCaseDiffersFromTheCatalog(): void
    {
        $productId = (int)DataFixtureStorageManager::getStorage()->get('product')->getId();
        $lookup = Bootstrap::getObjectManager()->create(GetProductIdsBySkus::class);

        self::assertSame(['CASE-SIMPLE' => $productId], array_map('intval', $lookup->execute(['CASE-SIMPLE'])));
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'case-simple'], 'product')]
    public function testFindsTheTypeOfASkuWhoseCaseDiffersFromTheCatalog(): void
    {
        $lookup = Bootstrap::getObjectManager()->create(GetProductTypesBySkus::class);

        self::assertSame(['CASE-SIMPLE' => 'simple'], $lookup->execute(['CASE-SIMPLE']));
    }
}
