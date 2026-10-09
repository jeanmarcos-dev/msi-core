<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdjustmentOriginTest extends TestCase
{
    public function testOutsideARunThereIsNoOrigin(): void
    {
        $origin = new AdjustmentOrigin();

        self::assertNull($origin->getActor());
        self::assertNull($origin->getRequestId());
    }

    public function testTheInnerRunWinsUntilItEnds(): void
    {
        $origin = new AdjustmentOrigin();
        $outer = new Actor(ActorType::Admin, '1', 'jane');
        $inner = new Actor(ActorType::Import);

        $seen = $origin->run($outer, 'outer', fn () => [
            $origin->run($inner, 'inner', fn () => [$origin->getActor(), $origin->getRequestId()]),
            $origin->getActor(),
            $origin->getRequestId(),
        ]);

        self::assertSame([[$inner, 'inner'], $outer, 'outer'], $seen);
        self::assertNull($origin->getActor());
    }

    public function testAFailedOperationDropsItsOrigin(): void
    {
        $origin = new AdjustmentOrigin();

        try {
            $origin->run(new Actor(ActorType::Import), 'run', fn () => throw new RuntimeException('failed'));
        } catch (RuntimeException) {
            self::assertNull($origin->getRequestId());
            return;
        }
        self::fail('The failure was swallowed');
    }

    public function testResetDropsEveryOrigin(): void
    {
        $origin = new AdjustmentOrigin();

        $origin->run(new Actor(ActorType::Import), 'run', function () use ($origin): void {
            $origin->_resetState();
        });

        self::assertNull($origin->getActor());
    }
}
