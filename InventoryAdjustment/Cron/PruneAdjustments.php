<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\InventoryAdjustment\Model\Config;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentPruner;

class PruneAdjustments
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * @param Config $config
     * @param AdjustmentPruner $pruner
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly Config $config,
        private readonly AdjustmentPruner $pruner,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * Prune history rows older than the configured retention
     *
     * @return void
     */
    public function execute(): void
    {
        $retentionDays = $this->config->getRetentionDays();
        if ($retentionDays === 0) {
            return;
        }
        $cutoff = gmdate(
            'Y-m-d H:i:s',
            (int)$this->dateTime->gmtTimestamp() - $retentionDays * self::SECONDS_PER_DAY
        );
        $this->pruner->prune($cutoff, $this->config->getPruneBatchSize());
    }
}
