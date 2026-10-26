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
use Magento\InventoryStockVisualizer\Controller\FragmentResponder;
use Magento\InventoryStockVisualizer\Model\Availability\GetBundleMaxSellable;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;

/**
 * Return how many bundles can be sold for the customer's current selection.
 *
 * The answer depends only on the SKU, the selection and the website stock, so identical
 * selections share a cacheable fragment tagged with the chosen children's purge tags: any
 * stock change on a chosen child invalidates exactly the selections that include it.
 */
class BundleMax implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param Config $config
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetBundleMaxSellable $getBundleMaxSellable
     * @param LevelResolver $levelResolver
     * @param ResolveDisplayConfig $resolveDisplayConfig
     * @param FragmentResponder $responder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly GetBundleMaxSellable $getBundleMaxSellable,
        private readonly LevelResolver $levelResolver,
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly FragmentResponder $responder
    ) {
    }

    /**
     * Resolve the sellable-bundle count for the posted selection and emit it as JSON.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->responder->create();
        $sku = (string) $this->request->getParam('sku');
        $selections = $this->parseSelections($this->request->getParam('selections'));

        if (!$this->config->isEnabled() || $sku === '') {
            return $this->responder->uncacheable($result, null);
        }

        try {
            $stockId = $this->getStockIdForCurrentWebsite->execute();
            $bundleMax = $this->getBundleMaxSellable->execute($sku, $selections, $stockId);
        } catch (\Throwable $e) {
            return $this->responder->uncacheable($result, null);
        }

        $max = $bundleMax->getMax();
        $productIds = $bundleMax->getProductIds();
        $payload = $this->payload($sku, $max);

        if ($max === null || $productIds === []) {
            return $this->responder->uncacheable($result, $payload);
        }

        return $this->responder->cacheable(
            $result,
            [...$this->responder->resolveProductIds([$sku]), ...$productIds],
            $payload
        );
    }

    /**
     * Project the sellable count for the client.
     *
     * The exact count in quantity display, or a coarse level (never the number) in level display.
     *
     * @param string $sku
     * @param int|null $max
     * @return array<string, mixed>|null
     */
    private function payload(string $sku, ?int $max): ?array
    {
        if ($max === null) {
            return null;
        }
        $displayConfig = $this->resolveDisplayConfig->forSku($sku);
        if ($displayConfig->isLevel()) {
            return [
                'level' => $this->levelResolver->resolve((float) $max, $displayConfig),
                'salable' => $max > 0,
            ];
        }

        return ['max' => $max];
    }

    /**
     * Parse the chosen selections (selection id => customer qty) from the request.
     *
     * @param mixed $raw
     * @return array<int|string, float|int|string>
     */
    private function parseSelections($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }
}
