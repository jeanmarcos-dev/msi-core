<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\InputException;
use Magento\InventoryAdjustment\Model\Adjustment;
use Magento\InventoryAdjustment\Model\AdjustmentRepository;
use Magento\InventoryAdjustment\Model\AdjustmentSearchResults;
use Magento\InventoryAdjustment\Model\Config;
use Magento\InventoryAdjustment\Model\ResourceModel\Adjustment\Collection;
use Magento\InventoryAdjustment\Model\ResourceModel\Adjustment\CollectionFactory;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentSearchResultsInterfaceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdjustmentRepositoryTest extends TestCase
{
    /**
     * @var Collection|MockObject
     */
    private $collection;

    /**
     * @var CollectionProcessorInterface|MockObject
     */
    private $collectionProcessor;

    /**
     * @var array
     */
    private array $items = [];

    /**
     * @var AdjustmentRepository
     */
    private AdjustmentRepository $repository;

    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('getItems')->willReturnCallback(fn () => $this->items);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);
        $this->collectionProcessor = $this->createMock(CollectionProcessorInterface::class);
        $searchResultsFactory = $this->createMock(AdjustmentSearchResultsInterfaceFactory::class);
        $searchResultsFactory->method('create')->willReturnCallback(fn () => new AdjustmentSearchResults());
        $config = $this->createMock(Config::class);
        $config->method('getMaxPageSize')->willReturn(500);

        $this->repository = new AdjustmentRepository(
            $this->collectionProcessor,
            $collectionFactory,
            $searchResultsFactory,
            $config
        );
    }

    public function testWithoutAnOrderTheNewestComeFirst(): void
    {
        $this->collection->expects(self::once())->method('setOrder')->with('adjustment_id', 'DESC');

        $this->repository->getList($this->criteria(10, []));
    }

    public function testAnExplicitOrderIsKept(): void
    {
        $this->collection->expects(self::never())->method('setOrder');

        $oldestFirst = new SortOrder(['field' => 'created_at', 'direction' => 'ASC']);

        $this->repository->getList($this->criteria(10, [$oldestFirst]));
    }

    public function testWithoutAPageSizeThePageIsCapped(): void
    {
        $this->collection->expects(self::once())->method('setPageSize')->with(500);

        $this->repository->getList($this->criteria(null, []));
    }

    public function testAGivenPageWithinTheCapIsLeftToTheCriteria(): void
    {
        $this->collection->expects(self::never())->method('setPageSize');

        $this->repository->getList($this->criteria(500, []));
    }

    public function testAPageLargerThanTheCapIsRejected(): void
    {
        $this->collectionProcessor->expects(self::never())->method('process');
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('500');

        $this->repository->getList($this->criteria(501, []));
    }

    /**
     * @dataProvider pageSizesBelowOne
     */
    public function testAPageSmallerThanOneIsRejected(int $pageSize): void
    {
        $this->collectionProcessor->expects(self::never())->method('process');
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('between 1 and 500');

        $this->repository->getList($this->criteria($pageSize, []));
    }

    public static function pageSizesBelowOne(): array
    {
        return ['zero' => [0], 'negative' => [-5]];
    }

    public function testTheResultsCarryTheItemsAndTheWholeCount(): void
    {
        $item = $this->createMock(Adjustment::class);
        $this->items = [7 => $item];
        $this->collection->method('getSize')->willReturn(2000);
        $criteria = $this->criteria(1, []);

        $results = $this->repository->getList($criteria);

        self::assertSame([$item], $results->getItems());
        self::assertSame(2000, $results->getTotalCount());
        self::assertSame($criteria, $results->getSearchCriteria());
    }

    private function criteria(?int $pageSize, array $sortOrders): SearchCriteria
    {
        $criteria = new SearchCriteria();
        $criteria->setPageSize($pageSize);
        $criteria->setSortOrders($sortOrders);
        return $criteria;
    }
}
