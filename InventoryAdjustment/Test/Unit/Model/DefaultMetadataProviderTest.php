<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryAdjustment\Model\DefaultMetadataProvider;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use PHPUnit\Framework\TestCase;

class DefaultMetadataProviderTest extends TestCase
{
    public function testAdminChangesAreCorrections(): void
    {
        self::assertSame(AdjustmentReason::Correction, $this->reasonIn(Area::AREA_ADMINHTML));
    }

    public function testApiChangesAreOther(): void
    {
        self::assertSame(AdjustmentReason::Other, $this->reasonIn(Area::AREA_WEBAPI_REST));
    }

    public function testChangesWithoutAnAreaAreOther(): void
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));

        self::assertSame(AdjustmentReason::Other, (new DefaultMetadataProvider($state))->get()->getReason());
    }

    private function reasonIn(string $area): AdjustmentReason
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn($area);

        return (new DefaultMetadataProvider($state))->get()->getReason();
    }
}
