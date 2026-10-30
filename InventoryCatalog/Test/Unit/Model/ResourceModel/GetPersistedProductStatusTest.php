<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryCatalog\Model\ResourceModel\GetPersistedProductStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetPersistedProductStatusTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var Product|MockObject
     */
    private $product;

    /**
     * @var GetPersistedProductStatus
     */
    private $getPersistedProductStatus;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('row_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $attribute->method('getAttributeId')->willReturn('93');
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->with(Product::ENTITY, ProductInterface::STATUS)->willReturn($attribute);
        $this->product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getStoreId'])
            ->getMock();
        $this->product->method('getData')->with('row_id')->willReturn('7');
        $this->product->method('getStoreId')->willReturn(2);
        $this->getPersistedProductStatus = new GetPersistedProductStatus(
            $resourceConnection,
            $metadataPool,
            $eavConfig
        );
    }

    public function testItReadsTheValueOfTheWrittenStoreFallingBackToTheDefaultOne(): void
    {
        $this->select->method('from')->with('catalog_product_entity_int', ['value'])->willReturnSelf();
        $this->select->expects(self::exactly(3))
            ->method('where')
            ->willReturnCallback(function (string $condition, $value): Select {
                $expected = [
                    'row_id = ?' => '7',
                    'attribute_id = ?' => 93,
                    'store_id IN (?)' => [0, 2],
                ];
                self::assertSame($expected[$condition], $value);

                return $this->select;
            });
        $this->select->expects(self::once())->method('order')->with('store_id DESC')->willReturnSelf();
        $this->select->expects(self::once())->method('limit')->with(1)->willReturnSelf();
        $this->connection->expects(self::once())->method('fetchOne')->with($this->select)->willReturn('2');

        self::assertSame(2, $this->getPersistedProductStatus->execute($this->product));
    }

    public function testItReturnsNullWhenNoValueIsPersisted(): void
    {
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();
        $this->connection->method('fetchOne')->willReturn(false);

        self::assertNull($this->getPersistedProductStatus->execute($this->product));
    }
}
