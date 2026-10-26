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
use Magento\InventoryStockVisualizer\Model\Availability\GetGroupedSetsMax;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;

/**
 * Return the per-child availability of a composite product as a cacheable, tag-purgeable
 * JSON fragment, so the volatile child quantities never sit in the product page cache.
 *
 * The fragment is tagged with each child's dedicated purge tag: a stock change on one child
 * invalidates exactly the composites that list it, leaving the product page untouched.
 */
class Children implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param Config $config
     * @param GetStockViewInterface $getStockView
     * @param StockViewSerializer $stockViewSerializer
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetGroupedSetsMax $getGroupedSetsMax
     * @param FragmentResponder $responder
     * @param ResolveDisplayConfig $resolveDisplayConfig
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly GetStockViewInterface $getStockView,
        private readonly StockViewSerializer $stockViewSerializer,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly GetGroupedSetsMax $getGroupedSetsMax,
        private readonly FragmentResponder $responder,
        private readonly ResolveDisplayConfig $resolveDisplayConfig
    ) {
    }

    /**
     * Resolve the composite children availability and emit it as a purge-tagged JSON fragment.
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
        } catch (\Throwable $e) {
            return $this->responder->uncacheable($result, null);
        }

        if (!$view->isAggregateOnly()) {
            return $this->responder->uncacheable($result, null);
        }

        $data = $this->stockViewSerializer->serializeChildren($view);
        $sets = $this->resolveSets($sku, $stockId);
        if ($sets !== null) {
            $data['sets'] = $sets;
        }

        $productIds = $this->responder->resolveProductIds($this->childSkus($data['children']));
        if ($productIds === []) {
            return $this->responder->uncacheable($result, $data);
        }

        return $this->responder->cacheable(
            $result,
            [...$this->responder->resolveProductIds([$sku]), ...$productIds],
            $data
        );
    }

    /**
     * Maximum complete grouped sets, when the calculator applies to this product, else null.
     *
     * @param string $sku
     * @param int $stockId
     * @return int|null
     */
    private function resolveSets(string $sku, int $stockId): ?int
    {
        if (!$this->config->isGroupedSetsCalculatorEnabled()
            || $this->config->getGroupedMode() !== Config::COMPOSITE_MODE_CHILDREN
        ) {
            return null;
        }

        $sets = $this->getGroupedSetsMax->execute($sku, $stockId);
        if ($sets === null) {
            return null;
        }

        // Level display exposes no exact numbers, so the set count collapses to a coarse flag.
        if ($this->resolveDisplayConfig->forSku($sku)->isLevel()) {
            return $sets > 0 ? 1 : 0;
        }

        return $sets;
    }

    /**
     * Collect the child SKUs from the serialized children rows.
     *
     * @param array<int,array{sku:string,label:string,salable:bool,qty:float}> $children
     * @return string[]
     */
    private function childSkus(array $children): array
    {
        $skus = [];
        foreach ($children as $child) {
            $skus[] = $child['sku'];
        }

        return $skus;
    }
}
