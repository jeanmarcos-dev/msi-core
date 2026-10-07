<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Plugin\Sales\ResourceModel\Order\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;

/**
 * Give a new shipment that still has no source the one resolved within its order stock, or refuse to save it
 */
class ResolveSourceForShipmentPlugin
{
    /**
     * @param ResolveShipmentSourceCode $resolveShipmentSourceCode
     * @param ShipmentExtensionFactory $shipmentExtensionFactory
     */
    public function __construct(
        private readonly ResolveShipmentSourceCode $resolveShipmentSourceCode,
        private readonly ShipmentExtensionFactory $shipmentExtensionFactory
    ) {
    }

    /**
     * Resolve the source of a new shipment saved without one
     *
     * @param ShipmentResource $subject
     * @param AbstractModel $shipment
     * @return void
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(ShipmentResource $subject, AbstractModel $shipment): void
    {
        if (!$shipment instanceof Shipment || $shipment->getId()) {
            return;
        }
        $shipmentExtension = $shipment->getExtensionAttributes();
        if ($shipmentExtension && $shipmentExtension->getSourceCode()) {
            return;
        }

        $sourceCode = $this->resolveShipmentSourceCode->execute($shipment, $shipment->getOrder());
        if ($sourceCode === null) {
            throw new LocalizedException(
                __(
                    'Specify the source to ship from: no single source of the order stock can ship every item '
                    . 'of this shipment.'
                )
            );
        }

        $shipmentExtension = $shipmentExtension ?: $this->shipmentExtensionFactory->create();
        $shipmentExtension->setSourceCode($sourceCode);
        $shipment->setExtensionAttributes($shipmentExtension);
    }
}
