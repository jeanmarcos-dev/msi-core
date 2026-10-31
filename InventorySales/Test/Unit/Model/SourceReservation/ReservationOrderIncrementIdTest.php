<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\SourceReservation\ReservationOrderIncrementId;
use PHPUnit\Framework\TestCase;

class ReservationOrderIncrementIdTest extends TestCase
{
    /**
     * @var ReservationOrderIncrementId
     */
    private $model;

    protected function setUp(): void
    {
        $this->model = new ReservationOrderIncrementId(new Json());
    }

    public function testReadsTheColumnFirst(): void
    {
        $reservation = $this->reservation('000000007', '{"object_increment_id":"000000009"}');

        self::assertSame('000000007', $this->model->execute($reservation));
    }

    public function testFallsBackToTheMetadata(): void
    {
        $reservation = $this->reservation(null, '{"object_type":"order","object_increment_id":"000000009"}');

        self::assertSame('000000009', $this->model->execute($reservation));
    }

    public function testReturnsNullWithoutAnIncrementId(): void
    {
        self::assertNull($this->model->execute($this->reservation('', '{"object_type":"order"}')));
        self::assertNull($this->model->execute($this->reservation(null, '')));
        self::assertNull($this->model->execute($this->reservation(null, null)));
    }

    public function testReturnsNullForMetadataThatIsNotAnObject(): void
    {
        self::assertNull($this->model->execute($this->reservation(null, 'not json')));
        self::assertNull($this->model->execute($this->reservation(null, '"000000009"')));
    }

    private function reservation(?string $incrementId, ?string $metadata): ReservationInterface
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getObjectIncrementId')->willReturn($incrementId);
        $reservation->method('getMetadata')->willReturn($metadata);
        return $reservation;
    }
}
