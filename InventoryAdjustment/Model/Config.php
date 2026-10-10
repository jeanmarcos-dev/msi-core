<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_ENABLED = 'cataloginventory/source_item_adjustment/enabled';
    private const XML_PATH_MAX_PAGE_SIZE = 'cataloginventory/source_item_adjustment/max_page_size';
    private const XML_PATH_RETENTION_DAYS = 'cataloginventory/source_item_adjustment/retention_days';
    private const XML_PATH_PRUNE_BATCH_SIZE = 'cataloginventory/source_item_adjustment/prune_batch_size';
    private const DEFAULT_MAX_PAGE_SIZE = 500;
    private const DEFAULT_PRUNE_BATCH_SIZE = 5000;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Whether source item changes are recorded
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Largest page of history rows one read may ask for
     *
     * @return int
     */
    public function getMaxPageSize(): int
    {
        $maxPageSize = $this->readPositiveInt(self::XML_PATH_MAX_PAGE_SIZE);
        return $maxPageSize > 0 ? $maxPageSize : self::DEFAULT_MAX_PAGE_SIZE;
    }

    /**
     * Days a history row is kept before pruning, where 0 keeps it forever
     *
     * @return int
     */
    public function getRetentionDays(): int
    {
        return $this->readPositiveInt(self::XML_PATH_RETENTION_DAYS);
    }

    /**
     * Largest number of history rows one prune statement deletes
     *
     * @return int
     */
    public function getPruneBatchSize(): int
    {
        $batchSize = $this->readPositiveInt(self::XML_PATH_PRUNE_BATCH_SIZE);
        return $batchSize > 0 ? $batchSize : self::DEFAULT_PRUNE_BATCH_SIZE;
    }

    /**
     * Read a configured integer, or 0 when it is missing, not numeric or not positive
     *
     * @param string $path
     * @return int
     */
    private function readPositiveInt(string $path): int
    {
        $value = $this->scopeConfig->getValue($path);
        $number = is_numeric($value) ? (int)$value : 0;
        return max($number, 0);
    }
}
