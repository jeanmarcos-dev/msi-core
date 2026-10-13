<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\SalesInventory;

use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventorySales\Plugin\SalesInventory\ProcessReturnQtyOnCreditMemoPlugin;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesInventory\Model\Order\ReturnProcessor;

class CreditmemoReturnContextPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     */
    public function __construct(private readonly AdjustmentContextInterface $context)
    {
    }

    /**
     * Record a return to stock under the credit memo that caused it
     *
     * @param ProcessReturnQtyOnCreditMemoPlugin $subject
     * @param callable $proceed
     * @param ReturnProcessor $returnProcessor
     * @param callable $returnProcessorProceed
     * @param CreditmemoInterface $creditmemo
     * @param OrderInterface $order
     * @param array $returnToStockItems
     * @param bool $isAutoReturn
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAroundExecute(
        ProcessReturnQtyOnCreditMemoPlugin $subject,
        callable $proceed,
        ReturnProcessor $returnProcessor,
        callable $returnProcessorProceed,
        CreditmemoInterface $creditmemo,
        OrderInterface $order,
        array $returnToStockItems = [],
        bool $isAutoReturn = false
    ): void {
        $creditmemoId = $creditmemo->getEntityId();
        $arguments = [
            $returnProcessor,
            $returnProcessorProceed,
            $creditmemo,
            $order,
            $returnToStockItems,
            $isAutoReturn
        ];
        if ($this->context->getCurrent() !== null || !$creditmemoId) {
            $proceed(...$arguments);
            return;
        }
        $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::ReturnRestock, 'creditmemo', (string)$creditmemoId),
            fn () => $proceed(...$arguments)
        );
    }
}
