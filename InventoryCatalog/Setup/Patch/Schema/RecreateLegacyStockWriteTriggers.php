<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Setup\Patch\Schema;

use Exception;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteTriggers;
use Psr\Log\LoggerInterface;

/**
 * Replaces the legacy stock write triggers a store installed with an earlier release.
 */
class RecreateLegacyStockWriteTriggers implements SchemaPatchInterface
{
    /**
     * @param LegacyStockWriteTriggers $legacyStockWriteTriggers
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LegacyStockWriteTriggers $legacyStockWriteTriggers,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        try {
            $this->legacyStockWriteTriggers->install();
        } catch (Exception $e) {
            $this->logger->warning(
                'Could not recreate the legacy stock write detection triggers, so the ones installed by an '
                . 'earlier release stay in place. Grant the TRIGGER privilege and re-run setup:upgrade. '
                . 'Reason: ' . $e->getMessage()
            );
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [
            DetectLegacyStockWrites::class,
        ];
    }
}
