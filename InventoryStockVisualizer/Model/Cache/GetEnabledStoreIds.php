<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Store views that show the stock visualizer panel, for purges that run outside a storefront request.
 */
class GetEnabledStoreIds implements ResetAfterRequestInterface
{
    /**
     * @var int[]|null
     */
    private ?array $storeIds = null;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Ids of the store views where the panel is enabled.
     *
     * @return int[]
     */
    public function execute(): array
    {
        if ($this->storeIds === null) {
            $this->storeIds = [];
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = (int)$store->getId();
                if ($this->config->isEnabled($storeId)) {
                    $this->storeIds[] = $storeId;
                }
            }
        }

        return $this->storeIds;
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->storeIds = null;
    }
}
