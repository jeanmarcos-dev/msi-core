<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;

/**
 * Project an availability view into the minimal AJAX payload. In quantity display it carries the
 * exact numbers; in level display it resolves them to coarse levels server-side, so exact
 * quantities are never exposed to the client regardless of product type.
 */
class StockViewSerializer
{
    /**
     * @param Config $config
     * @param LevelResolver $levelResolver
     * @param ResolveDisplayConfig $resolveDisplayConfig
     */
    public function __construct(
        private readonly Config $config,
        private readonly LevelResolver $levelResolver,
        private readonly ResolveDisplayConfig $resolveDisplayConfig
    ) {
    }

    /**
     * Project an availability view into the minimal AJAX payload.
     *
     * Composite (aggregate-only) views carry no meaningful quantity or per-source breakdown, so
     * only the salable status is sent. In level display the number is replaced by a coarse level.
     *
     * @param StockViewInterface $view
     * @param bool $level
     * @return array<string, mixed>
     */
    public function serialize(StockViewInterface $view, bool $level = false): array
    {
        if ($view->isAggregateOnly()) {
            return ['aggregateOnly' => true, 'salable' => $view->isSalable()];
        }

        $displayConfig = $this->resolveDisplayConfig->forSku($view->getSku());
        if ($level || $displayConfig->isLevel()) {
            return $this->serializeLevel($view, $displayConfig);
        }

        $data = ['qty' => $view->getSalableQty()];

        if ($this->config->getScope() === Config::SCOPE_PER_SOURCE) {
            $sources = [];
            foreach ($view->getSources() as $source) {
                $sources[$source->getSourceCode()] = $source->getQty();
            }
            $data['sources'] = $sources;
        }

        return $data;
    }

    /**
     * Project the per-child breakdown of a composite view for the children fragment: the
     * aggregate salable status plus one row per child. The volatile child quantities live only
     * in this cacheable fragment; in level display each child carries a coarse level, not a number.
     *
     * @param StockViewInterface $view
     * @return array{salable: bool, children: array<int, array<string, mixed>>}
     */
    public function serializeChildren(StockViewInterface $view): array
    {
        $level = $this->resolveDisplayConfig->forSku($view->getSku())->isLevel();
        $children = [];
        foreach ($view->getChildren() as $child) {
            $row = [
                'sku' => $child->getSku(),
                'label' => $child->getLabel(),
                'salable' => $child->isSalable(),
            ];
            if ($level) {
                $row['level'] = $this->levelResolver->resolve(
                    $child->getQty(),
                    $this->resolveDisplayConfig->forSku($child->getSku())
                );
            } else {
                $row['qty'] = $child->getQty();
            }
            $children[] = $row;
        }

        return ['salable' => $view->isSalable(), 'children' => $children];
    }

    /**
     * Coarse-level projection of a quantity view: aggregate level plus an optional per-source map.
     *
     * The per-source scope adds a source_code => level map. No exact quantity leaves the server.
     *
     * @param StockViewInterface $view
     * @param DisplayConfig $displayConfig
     * @return array<string, mixed>
     */
    private function serializeLevel(StockViewInterface $view, DisplayConfig $displayConfig): array
    {
        $data = [
            'level' => $this->levelResolver->resolve($view->getSalableQty(), $displayConfig),
            'salable' => $view->getSalableQty() > 0.0,
        ];

        if ($this->config->getScope() === Config::SCOPE_PER_SOURCE) {
            $sources = [];
            foreach ($view->getSources() as $source) {
                $sources[$source->getSourceCode()] = $this->levelResolver->resolve($source->getQty(), $displayConfig);
            }
            $data['sources'] = $sources;
        }

        return $data;
    }
}
