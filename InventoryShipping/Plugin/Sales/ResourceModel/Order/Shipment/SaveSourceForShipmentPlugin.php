<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Plugin\Sales\ResourceModel\Order\Shipment;

use Magento\Framework\Model\AbstractModel;
use Magento\InventoryShipping\Model\ResourceModel\ShipmentSource\SaveShipmentSource;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;

class SaveSourceForShipmentPlugin
{
    /**
     * @var SaveShipmentSource
     */
    private $saveShipmentSource;

    /**
     * @param SaveShipmentSource $saveShipmentSource
     */
    public function __construct(
        SaveShipmentSource $saveShipmentSource
    ) {
        $this->saveShipmentSource = $saveShipmentSource;
    }

    /**
     * Stores the source code from the shipment extension attributes; without one, keeps the stored source.
     *
     * @param ShipmentResource $subject
     * @param ShipmentResource $result
     * @param AbstractModel $shipment
     * @return ShipmentResource
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(
        ShipmentResource $subject,
        ShipmentResource $result,
        AbstractModel $shipment
    ) {
        $sourceCode = $shipment->getExtensionAttributes()?->getSourceCode();
        if ($sourceCode) {
            $this->saveShipmentSource->execute((int)$shipment->getId(), $sourceCode);
        }

        return $result;
    }
}
