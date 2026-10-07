<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Plugin\Sales\Order\Validation;

use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Validation\ShipOrderInterface;
use Magento\Sales\Model\ValidatorResultInterface;

/**
 * Report a shipment without a source that no single source of the order stock can ship
 */
class ValidateSourceOnShipOrderPlugin
{
    /**
     * @param ResolveShipmentSourceCode $resolveShipmentSourceCode
     */
    public function __construct(
        private readonly ResolveShipmentSourceCode $resolveShipmentSourceCode
    ) {
    }

    /**
     * Add a validation message when the shipment source cannot be resolved
     *
     * @param ShipOrderInterface $subject
     * @param ValidatorResultInterface $result
     * @param mixed $order
     * @param mixed $shipment
     * @return ValidatorResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterValidate(
        ShipOrderInterface $subject,
        ValidatorResultInterface $result,
        $order,
        $shipment
    ): ValidatorResultInterface {
        if (!$order instanceof Order || !$shipment instanceof Shipment || $shipment->getId()) {
            return $result;
        }
        $shipmentExtension = $shipment->getExtensionAttributes();
        if ($shipmentExtension && $shipmentExtension->getSourceCode()) {
            return $result;
        }

        if ($this->resolveShipmentSourceCode->execute($shipment, $order) === null) {
            $result->addMessage(
                (string)__(
                    'Specify the source to ship from: no single source of the order stock can ship every item '
                    . 'of this shipment.'
                )
            );
        }

        return $result;
    }
}
