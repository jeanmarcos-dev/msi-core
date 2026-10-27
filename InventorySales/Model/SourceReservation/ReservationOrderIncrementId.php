<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;

class ReservationOrderIncrementId
{
    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Order increment id of the reservation, from its column or its metadata, or null when it has none.
     *
     * @param ReservationInterface $reservation
     * @return string|null
     */
    public function execute(ReservationInterface $reservation): ?string
    {
        $incrementId = (string)($reservation->getObjectIncrementId() ?? '');
        if ($incrementId !== '') {
            return $incrementId;
        }

        $metadata = $reservation->getMetadata();
        if ($metadata === null || $metadata === '') {
            return null;
        }
        try {
            $data = $this->serializer->unserialize($metadata);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        $incrementId = (string)($data['object_increment_id'] ?? '');

        return $incrementId === '' ? null : $incrementId;
    }
}
