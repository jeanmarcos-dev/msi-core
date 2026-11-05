<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryCatalog\Model\ResourceModel\GetProductTypesBySkus;
use PHPUnit\Framework\TestCase;

class GetProductTypesBySkusTest extends TestCase
{
    public function testKeysTheTypesBySkuAsRequested(): void
    {
        $model = $this->createModel(['24-MB01' => 'simple', 'MH01' => 'configurable']);

        $this->assertSame(
            ['24-MB01' => 'simple', 'MH01' => 'configurable'],
            $model->execute(['24-MB01', 'MH01'])
        );
    }

    public function testFindsASkuWhoseCaseDiffersFromTheCatalog(): void
    {
        $model = $this->createModel(['24-MB01' => 'simple']);

        $this->assertSame(['24-mb01' => 'simple'], $model->execute(['24-mb01']));
    }

    public function testKeepsSkusThatDifferOnlyInLeadingZerosApart(): void
    {
        $model = $this->createModel(['01234' => 'simple', '1234' => 'virtual']);

        $this->assertSame(['01234' => 'simple', '1234' => 'virtual'], $model->execute(['01234', '1234']));
    }

    public function testLeavesOutTheSkusTheCatalogDoesNotHave(): void
    {
        $model = $this->createModel(['24-MB01' => 'simple']);

        $this->assertSame(['24-MB01' => 'simple'], $model->execute(['24-MB01', 'missing']));
    }

    private function createModel(array $typesByStoredSkus): GetProductTypesBySkus
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->with($select)->willReturn($typesByStoredSkus);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new GetProductTypesBySkus($resource);
    }
}
