<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Plugin\Sales\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\InventoryShipping\Model\ResolveShipmentSourceCode;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;

class AssignSourceCodeToShipmentPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ShipmentExtensionFactory
     */
    private $shipmentExtensionFactory;

    /**
     * @var ResolveShipmentSourceCode
     */
    private $resolveShipmentSourceCode;

    /**
     * @param RequestInterface $request
     * @param ShipmentExtensionFactory $shipmentExtensionFactory
     * @param ResolveShipmentSourceCode $resolveShipmentSourceCode
     */
    public function __construct(
        RequestInterface $request,
        ShipmentExtensionFactory $shipmentExtensionFactory,
        ResolveShipmentSourceCode $resolveShipmentSourceCode
    ) {
        $this->request = $request;
        $this->shipmentExtensionFactory = $shipmentExtensionFactory;
        $this->resolveShipmentSourceCode = $resolveShipmentSourceCode;
    }

    /**
     * Sets the source code for a shipment from the request, or the source resolved within the order stock.
     *
     * @param ShipmentFactory $subject
     * @param ShipmentInterface $shipment
     * @param Order $order
     * @return ShipmentInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreate(ShipmentFactory $subject, ShipmentInterface $shipment, Order $order)
    {
        $sourceCode = $this->request->getParam('sourceCode');
        if (empty($sourceCode) && $shipment instanceof Shipment) {
            $sourceCode = $this->resolveShipmentSourceCode->execute($shipment, $order);
        }
        if (empty($sourceCode)) {
            return $shipment;
        }

        $shipmentExtension = $shipment->getExtensionAttributes();

        if (empty($shipmentExtension)) {
            $shipmentExtension = $this->shipmentExtensionFactory->create();
        }
        $shipmentExtension->setSourceCode($sourceCode);
        $shipment->setExtensionAttributes($shipmentExtension);

        return $shipment;
    }
}
