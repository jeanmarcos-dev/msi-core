<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;

class AdjustmentRowsBuilder
{
    private const PRECISION = 4;

    /**
     * Rows for every source item whose quantity or status differs between both snapshots
     *
     * @param array $before
     * @param array $after
     * @param AdjustmentMetadataInterface $metadata
     * @param Actor $actor
     * @return array
     */
    public function build(array $before, array $after, AdjustmentMetadataInterface $metadata, Actor $actor): array
    {
        $rows = [];
        foreach (array_keys($before + $after) as $sourceCode) {
            $skus = ($before[$sourceCode] ?? []) + ($after[$sourceCode] ?? []);
            foreach (array_keys($skus) as $sku) {
                $change = $this->getChange(
                    $before[$sourceCode][$sku] ?? null,
                    $after[$sourceCode][$sku] ?? null
                );
                if ($change === null) {
                    continue;
                }
                $rows[] = [
                    'source_code' => (string)$sourceCode,
                    'sku' => (string)$sku,
                    'state' => 'available',
                ] + $change + [
                    'reason' => $metadata->getReason()->value,
                    'actor_type' => $actor->type->value,
                    'actor_id' => $actor->id,
                    'actor_label' => $actor->label,
                    'reference_type' => $metadata->getReferenceType(),
                    'reference_id' => $metadata->getReferenceId(),
                    'request_id' => $metadata->getRequestId(),
                    'note' => $metadata->getNote(),
                ];
            }
        }

        return $rows;
    }

    /**
     * Delta, result and status change of one source item, or null when nothing worth recording changed
     *
     * @param array|null $old
     * @param array|null $new
     * @return array|null
     */
    private function getChange(?array $old, ?array $new): ?array
    {
        $oldQuantity = round($old['quantity'] ?? 0.0, self::PRECISION);
        $newQuantity = round($new['quantity'] ?? 0.0, self::PRECISION);
        if (($old === null && $newQuantity === 0.0) || ($new === null && $oldQuantity === 0.0)) {
            return null;
        }
        $delta = round($newQuantity - $oldQuantity, self::PRECISION);
        $oldStatus = $old['status'] ?? null;
        $newStatus = $new['status'] ?? null;
        $statusChanged = $oldStatus !== $newStatus;
        if ($delta === 0.0 && !$statusChanged) {
            return null;
        }

        return [
            'delta' => $delta,
            'quantity_after' => $newQuantity,
            'status_before' => $statusChanged ? $oldStatus : null,
            'status_after' => $statusChanged ? $newStatus : null,
        ];
    }
}
