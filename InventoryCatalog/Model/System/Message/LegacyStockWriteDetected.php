<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\System\Message;

use Exception;
use Magento\Framework\Notification\MessageInterface;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteLog;

/**
 * Warns in the admin when something wrote to the frozen CatalogInventory tables.
 */
class LegacyStockWriteDetected implements MessageInterface
{
    /**
     * @var array|null
     */
    private ?array $detections = null;

    /**
     * @param LegacyStockWriteLog $legacyStockWriteLog
     */
    public function __construct(private readonly LegacyStockWriteLog $legacyStockWriteLog)
    {
    }

    /**
     * @inheritdoc
     */
    public function getIdentity()
    {
        return 'inventory_legacy_stock_write_' . hash('sha256', (string)$this->getTotalWrites());
    }

    /**
     * @inheritdoc
     */
    public function isDisplayed()
    {
        return $this->getDetections() !== [];
    }

    /**
     * @inheritdoc
     */
    public function getText()
    {
        $detections = $this->getDetections();
        $tables = implode(', ', array_unique(array_column($detections, 'table_name')));

        return __(
            'Inventory management ignored %1 write(s) to %2. Those tables are frozen: nothing reads them '
            . 'back, so the code that wrote them changed no stock and reported no error. Point it at '
            . 'SourceItemsSaveInterface or StockRegistryInterface::updateStockItemBySku(). '
            . 'Run bin/magento inventory:legacy-stock:writes for the details.',
            $this->getTotalWrites(),
            $tables
        );
    }

    /**
     * @inheritdoc
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    /**
     * Detected writes, resolved once per request and never fatal for the admin.
     *
     * @return array
     */
    private function getDetections(): array
    {
        if ($this->detections === null) {
            try {
                $this->detections = $this->legacyStockWriteLog->getDetections();
            } catch (Exception $e) {
                $this->detections = [];
            }
        }

        return $this->detections;
    }

    /**
     * Total number of ignored writes.
     *
     * @return int
     */
    private function getTotalWrites(): int
    {
        return (int)array_sum(array_column($this->getDetections(), 'write_count'));
    }
}
