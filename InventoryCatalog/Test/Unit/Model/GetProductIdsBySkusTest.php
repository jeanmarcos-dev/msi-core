<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalog\Model\GetProductIdsBySkus;
use PHPUnit\Framework\TestCase;

class GetProductIdsBySkusTest extends TestCase
{
    public function testKeysTheIdsBySkuAsRequested(): void
    {
        $model = $this->createModel(['24-MB01', 'abc'], ['24-MB01' => '321', 'ABC' => '7']);

        $this->assertSame(['24-MB01' => '321', 'abc' => '7'], $model->execute(['24-MB01', 'abc']));
    }

    public function testFindsASkuWhoseCaseDiffersFromTheCatalog(): void
    {
        $model = $this->createModel(['24-mb01'], ['24-MB01' => '321']);

        $this->assertSame(['24-mb01' => '321'], $model->execute(['24-mb01']));
    }

    public function testKeepsSkusThatDifferOnlyInLeadingZerosApart(): void
    {
        $model = $this->createModel(['01234', '1234'], ['01234' => '1', '1234' => '2']);

        $this->assertSame(['01234' => '1', '1234' => '2'], $model->execute(['01234', '1234']));
    }

    public function testThrowsForTheSkusTheCatalogDoesNotHave(): void
    {
        $model = $this->createModel(['24-mb01', 'missing'], ['24-MB01' => '321']);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Following products with requested skus were not found: missing');

        $model->execute(['24-mb01', 'missing']);
    }

    private function createModel(array $requestedSkus, array $idsByStoredSkus): GetProductIdsBySkus
    {
        $productResource = $this->createMock(ProductResourceModel::class);
        $productResource->method('getProductsIdsBySkus')->with($requestedSkus)->willReturn($idsByStoredSkus);

        return new GetProductIdsBySkus($productResource);
    }
}
