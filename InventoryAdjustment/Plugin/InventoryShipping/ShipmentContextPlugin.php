<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryShipping;

use Magento\Framework\Event\Observer;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryShipping\Observer\SourceDeductionProcessor;

class ShipmentContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record the deduction of a new shipment under that shipment
     *
     * @param SourceDeductionProcessor $subject
     * @param callable $proceed
     * @param Observer $observer
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(SourceDeductionProcessor $subject, callable $proceed, Observer $observer): mixed
    {
        $shipment = $observer->getEvent()->getData('shipment');
        if ($shipment === null || $shipment->getOrigData('entity_id')) {
            return $proceed($observer);
        }

        return $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::Shipment, 'shipment', (string)$shipment->getEntityId()),
            fn () => $proceed($observer)
        );
    }
}
