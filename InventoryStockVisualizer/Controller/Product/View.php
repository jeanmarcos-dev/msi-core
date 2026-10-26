<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Controller\Product;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Controller\FragmentResponder;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;

/**
 * Return the quantity availability of a product as a cacheable, tag-purgeable JSON fragment.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param Config $config
     * @param GetStockViewInterface $getStockView
     * @param StockViewSerializer $stockViewSerializer
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param FragmentResponder $responder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly GetStockViewInterface $getStockView,
        private readonly StockViewSerializer $stockViewSerializer,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly FragmentResponder $responder
    ) {
    }

    /**
     * Resolve the availability for the requested SKU and emit it as a cacheable fragment.
     *
     * The stock and product id are derived server-side from the website context and the
     * SKU, so request-supplied ids cannot decouple the cache tag from the data or flood
     * the cache key. A level display, the product's own or the requested one, never sends a quantity.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->responder->create();
        $sku = (string) $this->request->getParam('sku');
        if ($sku === '') {
            $sku = $this->responder->resolveSkuFromProductId((int) $this->request->getParam('product_id'));
        }

        if (!$this->config->isEnabled() || $sku === '') {
            return $this->responder->uncacheable($result, null);
        }

        try {
            $stockId = $this->getStockIdForCurrentWebsite->execute();
            $view = $this->getStockView->execute($sku, $stockId);
            $data = $this->stockViewSerializer->serialize($view, (bool) $this->request->getParam('level'));
            $productId = $this->responder->resolveProductId($sku);
        } catch (\Throwable $e) {
            return $this->responder->uncacheable($result, null);
        }

        if ($productId <= 0) {
            return $this->responder->uncacheable($result, null);
        }

        return $this->responder->cacheable($result, [$productId], $data);
    }
}
