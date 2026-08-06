<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryIndexer\Model\AreMultipleProductsSalable;
use Magento\InventorySales\Model\IsProductSalableResult;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterfaceFactory;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see AreMultipleProductsSalable
 */
class AreMultipleProductsSalableTest extends TestCase
{
    /**
     * @var GetStockItemsDataInterface|MockObject
     */
    private $getStockItemsData;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var AreMultipleProductsSalable
     */
    private $areMultipleProductsSalable;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->getStockItemsData = $this->createMock(GetStockItemsDataInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $factory = $this->createMock(IsProductSalableResultInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): IsProductSalableResultInterface => new IsProductSalableResult(
                $data['sku'],
                $data['stockId'],
                $data['isSalable']
            )
        );

        $this->areMultipleProductsSalable = new AreMultipleProductsSalable(
            $this->getStockItemsData,
            $factory,
            $this->logger
        );
    }

    /**
     * The index answer is carried over for every SKU it covers.
     *
     * @return void
     */
    public function testReportsTheSalabilityTheIndexHolds(): void
    {
        $this->getStockItemsData->method('execute')->willReturn([
            'sku1' => [GetStockItemsDataInterface::IS_SALABLE => true],
            'sku2' => [GetStockItemsDataInterface::IS_SALABLE => false],
        ]);

        $this->assertSame(
            ['sku1' => true, 'sku2' => false],
            $this->flatten($this->areMultipleProductsSalable->execute(['sku1', 'sku2'], 123))
        );
    }

    /**
     * A SKU the index has no row for still gets an answer, and that answer is not salable.
     *
     * Without it the result set is shorter than the SKU list, and callers that read it
     * positionally — the stock status assigned to a loaded product, among others — get
     * nothing back where they expect a result.
     *
     * @return void
     */
    public function testAnswersForSkusMissingFromTheIndex(): void
    {
        $this->getStockItemsData->method('execute')->willReturn([
            'indexed' => [GetStockItemsDataInterface::IS_SALABLE => true],
        ]);

        $this->assertSame(
            ['indexed' => true, 'missing' => false],
            $this->flatten($this->areMultipleProductsSalable->execute(['indexed', 'missing'], 123))
        );
    }

    /**
     * A failure to read the index leaves every SKU not salable, and is logged.
     *
     * @return void
     */
    public function testTreatsAnUnreadableIndexAsNotSalable(): void
    {
        $this->getStockItemsData->method('execute')
            ->willThrowException(new LocalizedException(__('Error fetching stock data')));
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('Error fetching stock data'));

        $this->assertSame(
            ['sku1' => false, 'sku2' => false],
            $this->flatten($this->areMultipleProductsSalable->execute(['sku1', 'sku2'], 123))
        );
    }

    /**
     * @param IsProductSalableResultInterface[] $results
     * @return array<string, bool>
     */
    private function flatten(array $results): array
    {
        $flattened = [];
        foreach ($results as $result) {
            $flattened[$result->getSku()] = $result->isSalable();
        }

        return $flattened;
    }
}
