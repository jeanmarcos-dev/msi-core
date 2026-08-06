<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryStockVisualizer\Model\Config;

/**
 * Apply the panel layout handle to the product page when the visualizer is on.
 *
 * The handle carries the panel block together with the removal of the core availability
 * badges it stands in for. Both belong to the same decision, so neither can live in the
 * unconditional layout: leaving the removals there strips the badge on an install where
 * the panel is switched off, and the product page ends up stating no availability at all.
 */
class AddPanelLayoutHandle implements ObserverInterface
{
    private const PRODUCT_PAGE_ACTION = 'catalog_product_view';

    private const HANDLE = 'inventory_stockviz_panel';

    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Add the panel handle to the layout being loaded.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        if ((string) $event->getData('full_action_name') !== self::PRODUCT_PAGE_ACTION) {
            return;
        }

        if (!$this->config->isEnabled()) {
            return;
        }

        $layout = $event->getData('layout');
        if ($layout === null) {
            return;
        }

        $layout->getUpdate()->addHandle(self::HANDLE);
    }
}
