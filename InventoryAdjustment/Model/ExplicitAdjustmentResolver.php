<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\Exception\InputException;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentInputInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

class ExplicitAdjustmentResolver
{
    private const MAX_LENGTHS = ['note' => 255, 'reference_type' => 32, 'reference_id' => 64];
    private const SALES_DOCUMENT_TYPES = ['order', 'shipment', 'invoice', 'creditmemo'];

    /**
     * Metadata the caller asked for, or null when no item carries an adjustment
     *
     * @param array $adjustments
     * @return AdjustmentMetadataInterface|null
     * @throws InputException
     */
    public function resolve(array $adjustments): ?AdjustmentMetadataInterface
    {
        $values = array_map(
            fn (?AdjustmentInputInterface $input) => $input ? $this->getValues($input) : null,
            $adjustments
        );
        $given = array_filter($values, fn (?array $value) => $value !== null);
        if ($given === []) {
            return null;
        }
        if (count($given) !== count($values) || count(array_unique(array_map('serialize', $given))) !== 1) {
            throw new InputException(__('Every source item of one request must carry the same adjustment.'));
        }
        $value = reset($given);
        $this->validateLengths($value);
        $this->validateReferenceType($value['reference_type']);

        return new AdjustmentMetadata(
            $this->getReason($value['reason']),
            $value['reference_type'],
            $value['reference_id'],
            null,
            $value['note']
        );
    }

    /**
     * Values of one adjustment input
     *
     * @param AdjustmentInputInterface $input
     * @return array
     */
    private function getValues(AdjustmentInputInterface $input): array
    {
        return [
            'reason' => $input->getReason(),
            'note' => $input->getNote(),
            'reference_type' => $input->getReferenceType(),
            'reference_id' => $input->getReferenceId(),
        ];
    }

    /**
     * Reason of the closed list named by a code
     *
     * @param string|null $code
     * @return AdjustmentReason
     * @throws InputException
     */
    private function getReason(?string $code): AdjustmentReason
    {
        $reason = $code === null ? null : AdjustmentReason::tryFrom($code);
        if ($reason === null) {
            throw new InputException(__(
                'The adjustment reason "%1" is not one of: %2.',
                (string)$code,
                implode(', ', array_map(fn (AdjustmentReason $case) => $case->value, AdjustmentReason::cases()))
            ));
        }

        return $reason;
    }

    /**
     * Reject the reference types Magento records itself with the document entity id
     *
     * @param string|null $referenceType
     * @return void
     * @throws InputException
     */
    private function validateReferenceType(?string $referenceType): void
    {
        if (in_array($referenceType, self::SALES_DOCUMENT_TYPES, true)) {
            throw new InputException(__(
                'The adjustment reference type "%1" is reserved for the sales documents Magento records: %2.',
                $referenceType,
                implode(', ', self::SALES_DOCUMENT_TYPES)
            ));
        }
    }

    /**
     * Reject values longer than the history columns they are stored in
     *
     * @param array $value
     * @return void
     * @throws InputException
     */
    private function validateLengths(array $value): void
    {
        foreach (self::MAX_LENGTHS as $field => $maxLength) {
            if ($value[$field] !== null && mb_strlen($value[$field]) > $maxLength) {
                throw new InputException(__('The adjustment %1 can be at most %2 characters.', $field, $maxLength));
            }
        }
    }
}
