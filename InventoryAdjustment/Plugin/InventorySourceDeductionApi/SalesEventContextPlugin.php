<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventorySourceDeductionApi;

use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;

class SalesEventContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record a deduction under the sales event that caused it
     *
     * @param SourceDeductionServiceInterface $subject
     * @param callable $proceed
     * @param SourceDeductionRequestInterface $request
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        SourceDeductionServiceInterface $subject,
        callable $proceed,
        SourceDeductionRequestInterface $request
    ): void {
        if ($this->context->getCurrent() !== null) {
            $proceed($request);
            return;
        }
        $salesEvent = $request->getSalesEvent();
        $this->context->run(
            new AdjustmentMetadata(
                $this->getReason((string)$salesEvent->getType()),
                (string)$salesEvent->getObjectType(),
                (string)$salesEvent->getObjectId()
            ),
            fn () => $proceed($request)
        );
    }

    /**
     * Reason for a sales event type
     *
     * @param string $type
     * @return AdjustmentReason
     */
    private function getReason(string $type): AdjustmentReason
    {
        return match ($type) {
            SalesEventInterface::EVENT_SHIPMENT_CREATED,
            SalesEventInterface::EVENT_INVOICE_CREATED => AdjustmentReason::Shipment,
            SalesEventInterface::EVENT_CREDITMEMO_CREATED => AdjustmentReason::ReturnRestock,
            default => AdjustmentReason::Other,
        };
    }
}
