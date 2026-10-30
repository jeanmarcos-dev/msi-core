<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\InventoryConfigurableProduct\Model\GetParentIdsWithSalableChildren;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SalableChildrenTest extends TestCase
{
    /**
     * @var GetParentIdsWithSalableChildren|MockObject
     */
    private $getParentIds;

    /**
     * @var SalableChildren
     */
    private SalableChildren $salableChildren;

    protected function setUp(): void
    {
        $this->getParentIds = $this->createMock(GetParentIdsWithSalableChildren::class);
        $this->salableChildren = new SalableChildren($this->getParentIds);
    }

    public function testPreloadResolvesEveryParentWithASingleQuery(): void
    {
        $withChildren = $this->createParent(1);
        $withoutChildren = $this->createParent(2);
        $this->getParentIds->expects(self::once())
            ->method('execute')
            ->with([$withChildren, $withoutChildren], 4)
            ->willReturn([1]);

        $this->salableChildren->preload([$withChildren, $withoutChildren], 4);

        self::assertTrue($this->salableChildren->exist($withChildren, 4));
        self::assertFalse($this->salableChildren->exist($withoutChildren, 4));
    }

    public function testUnresolvedParentIsABatchOfOne(): void
    {
        $parent = $this->createParent(7);
        $this->getParentIds->expects(self::once())
            ->method('execute')
            ->with([$parent], 1)
            ->willReturn([7]);

        self::assertTrue($this->salableChildren->exist($parent, 1));
    }

    public function testResolvedAnswerIsNotQueriedAgain(): void
    {
        $parent = $this->createParent(7);
        $this->getParentIds->expects(self::once())->method('execute')->willReturn([]);

        self::assertFalse($this->salableChildren->exist($parent, 1));
        self::assertFalse($this->salableChildren->exist($parent, 1));
    }

    public function testPreloadSkipsParentsAlreadyResolved(): void
    {
        $first = $this->createParent(1);
        $second = $this->createParent(2);
        $this->getParentIds->expects(self::exactly(2))
            ->method('execute')
            ->willReturnMap([
                [[$first], 1, [1]],
                [[$second], 1, [2]],
            ]);

        $this->salableChildren->preload([$first], 1);
        $this->salableChildren->preload([$first, $second], 1);

        self::assertTrue($this->salableChildren->exist($first, 1));
        self::assertTrue($this->salableChildren->exist($second, 1));
    }

    public function testAnswersAreKeptPerStore(): void
    {
        $parent = $this->createParent(3);
        $this->getParentIds->expects(self::exactly(2))
            ->method('execute')
            ->willReturnMap([
                [[$parent], 1, [3]],
                [[$parent], 2, []],
            ]);

        self::assertTrue($this->salableChildren->exist($parent, 1));
        self::assertFalse($this->salableChildren->exist($parent, 2));
    }

    public function testAnswersAreKeptPerProductInstance(): void
    {
        $loaded = $this->createParent(3);
        $reloaded = $this->createParent(3);
        $this->getParentIds->expects(self::exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls([3], []);

        self::assertTrue($this->salableChildren->exist($loaded, 1));
        self::assertFalse($this->salableChildren->exist($reloaded, 1));
    }

    public function testPreloadWithoutParentsDoesNotQuery(): void
    {
        $this->getParentIds->expects(self::never())->method('execute');

        $this->salableChildren->preload([], 1);
    }

    /**
     * Build a parent product stub with the given id.
     *
     * @param int $id
     * @return Product|MockObject
     */
    private function createParent(int $id)
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }
}
