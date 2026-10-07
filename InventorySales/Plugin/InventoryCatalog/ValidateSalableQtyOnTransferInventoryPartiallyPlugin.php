<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemDataBySkusAndSources;

/**
 * Refuse to transfer units of the origin source that open orders already reserve there.
 */
class ValidateSalableQtyOnTransferInventoryPartiallyPlugin
{
    /**
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param GetSourceItemDataBySkusAndSources $getSourceItemData
     * @param GetReservationsQuantityBySkusAndSources $getReservationsQuantity
     */
    public function __construct(
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly GetSourceItemDataBySkusAndSources $getSourceItemData,
        private readonly GetReservationsQuantityBySkusAndSources $getReservationsQuantity
    ) {
    }

    /**
     * Check the transfer against the origin quantity net of its source reservations.
     *
     * @param TransferInventoryPartially $subject
     * @param PartialInventoryTransferItemInterface $transfer
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @return void
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeExecute(
        TransferInventoryPartially $subject,
        PartialInventoryTransferItemInterface $transfer,
        string $originSourceCode,
        string $destinationSourceCode
    ): void {
        if (!$this->sourceReservationsConfig->isEnabled()) {
            return;
        }

        $sku = $transfer->getSku();
        $sourceItemData = $this->getSourceItemData->execute([$sku], [$originSourceCode]);
        $reservations = $this->getReservationsQuantity->execute([$sku], [$originSourceCode]);
        $quantity = $sourceItemData[$originSourceCode][$sku]['quantity'] ?? 0.0;
        $reserved = $reservations[$originSourceCode][$sku] ?? 0.0;

        if ($quantity + $reserved < $transfer->getQty()) {
            throw new ValidationException(
                __('Requested transfer amount for sku %sku is not available', ['sku' => $sku])
            );
        }
    }
}
