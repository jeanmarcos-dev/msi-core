<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Relation;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryCatalog\Model\GetChildrenSkusOfParentSkus;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetChildrenSkusOfParentSkusTest extends TestCase
{
    /**
     * @var Relation|MockObject
     */
    private $productRelationResourceMock;

    /**
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkusMock;

    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $getSkusByProductIdsMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connectionMock;

    /**
     * @var GetChildrenSkusOfParentSkus
     */
    private $model;

    protected function setUp(): void
    {
        $this->productRelationResourceMock = $this->createMock(Relation::class);
        $this->productRelationResourceMock->method('getMainTable')->willReturn('catalog_product_relation');
        $this->getProductIdsBySkusMock = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->getSkusByProductIdsMock = $this->createMock(GetSkusByProductIdsInterface::class);

        $select = $this->createMock(Select::class);
        foreach (['from', 'joinInner', 'where'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->connectionMock->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connectionMock);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);

        $this->model = new GetChildrenSkusOfParentSkus(
            $this->productRelationResourceMock,
            $this->getProductIdsBySkusMock,
            $this->getSkusByProductIdsMock,
            $resourceConnection,
            $metadataPool
        );
    }

    public function testExecuteNoSkus(): void
    {
        $this->getProductIdsBySkusMock->expects(self::never())->method('execute');
        $this->connectionMock->expects(self::never())->method('fetchAll');
        $this->model->execute([]);
    }

    public function testExecute(): void
    {
        $childrenSkusOfParentSkus = [
            'configurable1' => ['simple-1'],
            'grouped1' => [],
            'bundle1' => ['simple-1', 'simple-2'],
        ];

        $this->getProductIdsBySkusMock->expects(self::once())->method('execute')
            ->with(array_keys($childrenSkusOfParentSkus))
            ->willReturn(['configurable1' => 10, 'grouped1' => 20, 'bundle1' => 30]);
        $this->connectionMock->expects(self::once())->method('fetchAll')->willReturn([
            ['entity_id' => 10, 'child_id' => 2],
            ['entity_id' => 30, 'child_id' => 2],
            ['entity_id' => 30, 'child_id' => 3],
        ]);
        $this->getSkusByProductIdsMock->expects(self::once())->method('execute')
            ->with(self::equalToCanonicalizing([2, 3]))
            ->willReturn([2 => 'simple-1', 3 => 'simple-2']);

        $result = $this->model->execute(array_keys($childrenSkusOfParentSkus));
        self::assertEquals($childrenSkusOfParentSkus, $result);
    }
}
