<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\FlagManager;
use Magento\InventorySales\Model\SourceReservation\AbandonedPlacementMark;
use PHPUnit\Framework\TestCase;

class AbandonedPlacementMarkTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const FLAG_CODE = 'inventory_abandoned_placement_mark';

    /**
     * @var array<string,mixed>
     */
    private array $flags = [];

    public function testWithoutAMarkThereIsNoCutoffAndAdvanceRecordsTheNewestReservation(): void
    {
        $mark = $this->mark(42);

        $this->assertNull($mark->current());
        $mark->advance();

        $this->assertSame(['reservation_id' => 42, 'recorded_at' => self::NOW], $this->flags[self::FLAG_CODE]);
    }

    public function testAYoungMarkIsNotACutoffAndIsNeverReplaced(): void
    {
        $this->flags[self::FLAG_CODE] = ['reservation_id' => 7, 'recorded_at' => self::NOW - 899];
        $mark = $this->mark(42);

        $this->assertNull($mark->current());
        $mark->advance();

        $this->assertSame(['reservation_id' => 7, 'recorded_at' => self::NOW - 899], $this->flags[self::FLAG_CODE]);
    }

    public function testAMarkOldEnoughIsTheCutoffAndAdvanceReplacesIt(): void
    {
        $this->flags[self::FLAG_CODE] = ['reservation_id' => 7, 'recorded_at' => self::NOW - 900];
        $mark = $this->mark(42);

        $this->assertSame(7, $mark->current());
        $mark->advance();

        $this->assertSame(['reservation_id' => 42, 'recorded_at' => self::NOW], $this->flags[self::FLAG_CODE]);
    }

    public function testAMalformedMarkCountsAsNoMark(): void
    {
        $this->flags[self::FLAG_CODE] = 'garbage';
        $mark = $this->mark(42);

        $this->assertNull($mark->current());
        $mark->advance();

        $this->assertSame(['reservation_id' => 42, 'recorded_at' => self::NOW], $this->flags[self::FLAG_CODE]);
    }

    public function testAMarkAboveTheNewestReservationIsDiscardedAfterTheTableWasReset(): void
    {
        $this->flags[self::FLAG_CODE] = ['reservation_id' => 5_000_000, 'recorded_at' => self::NOW - 3600];
        $mark = $this->mark(12);

        $this->assertNull($mark->current());
        $mark->advance();

        $this->assertSame(['reservation_id' => 12, 'recorded_at' => self::NOW], $this->flags[self::FLAG_CODE]);
    }

    public function testAMarkFromTheFutureIsDiscarded(): void
    {
        $this->flags[self::FLAG_CODE] = ['reservation_id' => 7, 'recorded_at' => self::NOW + 3600];
        $mark = $this->mark(42);

        $this->assertNull($mark->current());
        $mark->advance();

        $this->assertSame(['reservation_id' => 42, 'recorded_at' => self::NOW], $this->flags[self::FLAG_CODE]);
    }

    public function testAnEmptyTableRecordsAMarkAtZero(): void
    {
        $mark = $this->mark(null);

        $mark->advance();

        $this->assertSame(['reservation_id' => 0, 'recorded_at' => self::NOW], $this->flags[self::FLAG_CODE]);
    }

    /**
     * @param int|null $newestReservationId
     * @return AbandonedPlacementMark
     */
    private function mark(?int $newestReservationId): AbandonedPlacementMark
    {
        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->method('getFlagData')->willReturnCallback(fn(string $code) => $this->flags[$code] ?? null);
        $flagManager->method('saveFlag')->willReturnCallback(
            function (string $code, $value) {
                $this->flags[$code] = $value;
                return true;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn([
            'newest' => $newestReservationId === null ? null : (string)$newestReservationId,
            'now' => (string)self::NOW,
        ]);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return new AbandonedPlacementMark($flagManager, $resourceConnection);
    }
}
