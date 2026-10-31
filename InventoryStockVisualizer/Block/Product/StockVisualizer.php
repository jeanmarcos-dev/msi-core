<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\Level;

/**
 * Product-page "Availability" panel.
 *
 * @api
 */
class StockVisualizer extends Template implements IdentityInterface
{
    public const KIND_NONE = '';

    public const KIND_QUANTITY = 'quantity';

    public const KIND_VARIANT = 'variant';

    public const KIND_CHILDREN = 'children';

    public const KIND_BUNDLE_MAX = 'bundleMax';

    /**
     * @var DisplayConfig|null
     */
    private $displayConfig;

    /**
     * @var int|null
     */
    private $stockId;

    /**
     * @var bool
     */
    private $stockResolved = false;

    /**
     * @var \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface|null
     */
    private $view;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param Json $json
     * @param AvailabilityData $availabilityData
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly Json $json,
        private readonly AvailabilityData $availabilityData,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the panel should render on the current product page.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->getProduct() !== null && $this->getStockId() !== null;
    }

    /**
     * Whether the panel shows a coarse level (semaphore) instead of the number.
     *
     * @return bool
     */
    public function isLevelMode(): bool
    {
        return $this->getDisplayConfig()->isLevel();
    }

    /**
     * Whether the panel breaks availability down per source.
     *
     * @return bool
     */
    public function isPerSource(): bool
    {
        return $this->config->getScope() === Config::SCOPE_PER_SOURCE;
    }

    /**
     * Whether the quantity is fetched on a call-to-action click rather than on page load.
     *
     * When on demand, the initial state (status word plus the call-to-action) is rendered
     * server-side and the volatile numbers stay hidden until the fetch, so the widget never
     * flashes a loading skeleton before it mounts.
     *
     * @return bool
     */
    public function isOnDemand(): bool
    {
        return $this->config->getMode() === Config::MODE_ON_DEMAND;
    }

    /**
     * Panel heading.
     *
     * @return string
     */
    public function getPanelTitle(): string
    {
        return (string) __('Availability');
    }

    /**
     * Aggregate level (level mode).
     *
     * @return string
     */
    public function getAggregateLevel(): string
    {
        $view = $this->getView();
        if ($view->isAggregateOnly()) {
            return $view->isSalable() ? Level::HIGH : Level::OUT;
        }

        return $this->availabilityData->resolveLevel($view->getSalableQty(), $this->getDisplayConfig());
    }

    /**
     * Whether the panel shows only an aggregate salable/not-salable status.
     *
     * True for composite types (configurable/grouped/bundle): no quantity number, no
     * per-source breakdown and no AJAX fetch — just the in-stock/out-of-stock word.
     *
     * @return bool
     */
    public function isAggregateStatusOnly(): bool
    {
        return $this->getView()->isAggregateOnly();
    }

    /**
     * Child structure scaffold (sku and label) for the composite children fragment.
     *
     * Only the stable structure is server-rendered; the volatile per-child stock arrives over AJAX.
     *
     * @return array<int, array{sku: string, label: string}>
     */
    public function getChildScaffold(): array
    {
        $rows = [];
        foreach ($this->getView()->getChildren() as $child) {
            $rows[] = [
                'sku' => $child->getSku(),
                'label' => $child->getLabel(),
            ];
        }

        return $rows;
    }

    /**
     * Server-rendered aggregate status for quantity mode: in stock or out of stock.
     *
     * The status is a coarse salability fact already cached with the product page, so it
     * is baked into the HTML; only the exact number is fetched over AJAX. The widget
     * reconciles it if a live quantity ever contradicts the cached status.
     *
     * @return string
     */
    public function getQuantityStatusLevel(): string
    {
        return $this->getView()->isSalable() ? Level::HIGH : Level::OUT;
    }

    /**
     * Per-source level rows (level mode), honouring hide-empty.
     *
     * @return array<int, array{name: string, level: string}>
     */
    public function getLevelSources(): array
    {
        $hideEmpty = $this->config->hideEmptySources();
        $rows = [];
        foreach ($this->getView()->getSources() as $source) {
            $qty = $source->getQty();
            if ($hideEmpty && $qty <= 0.0) {
                continue;
            }
            $rows[] = [
                'name' => (string) $source->getName(),
                'level' => $this->availabilityData->resolveLevel($qty, $this->getDisplayConfig()),
            ];
        }

        return $rows;
    }

    /**
     * Per-source scaffold rows (quantity mode) - labels only, numbers arrive over AJAX.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function getScaffoldSources(): array
    {
        return $this->availabilityData->enabledSources((int) $this->getStockId());
    }

    /**
     * Whether per-source labels are shown.
     *
     * @return bool
     */
    public function showSourceLabels(): bool
    {
        return $this->config->showSourceLabels();
    }

    /**
     * The interactive strategy for the current product.
     *
     * Returns KIND_NONE when the panel is fully server-rendered (level / aggregate-status /
     * children / grouped-sets) and needs no client component.
     *
     * @return string
     */
    public function getComponentKind(): string
    {
        return $this->isOutOfStock() ? self::KIND_NONE : $this->resolveBaseKind();
    }

    /**
     * Whether the panel already knows the product is unavailable.
     *
     * Suppressing the component on a known zero is what keeps an out-of-stock page from
     * offering a call-to-action — or firing an instant fetch — whose only possible answer
     * is the quantity the server already resolved. Aggregate salability is authoritative
     * for composites too: an out-of-stock parent has no salable child to report.
     *
     * @return bool
     */
    public function isOutOfStock(): bool
    {
        $kind = $this->resolveBaseKind();

        return $kind !== self::KIND_NONE && $this->resolveStatusLevel($kind) === Level::OUT;
    }

    /**
     * The interactive strategy the product maps to, before the availability guard.
     *
     * @return string
     */
    private function resolveBaseKind(): string
    {
        if ($this->isVariantMode()) {
            return self::KIND_VARIANT;
        }
        if ($this->isBundleMaxMode()) {
            return self::KIND_BUNDLE_MAX;
        }
        if ($this->getCompositeMode() === Config::COMPOSITE_MODE_CHILDREN) {
            return self::KIND_CHILDREN;
        }
        if ($this->isAggregateStatusOnly()) {
            return self::KIND_NONE;
        }
        if ($this->isLevelMode()) {
            return self::KIND_NONE;
        }

        return self::KIND_QUANTITY;
    }

    /**
     * Server-resolved status level backing the given strategy.
     *
     * @param string $kind
     * @return string
     */
    private function resolveStatusLevel(string $kind): string
    {
        return $kind === self::KIND_QUANTITY ? $this->getQuantityStatusLevel() : $this->getAggregateLevel();
    }

    /**
     * Mount payload for the Knockout availability component.
     *
     * The x-magento-init config seeds the component observables with the server-rendered
     * state, so hydration produces no visible change.
     *
     * @return string
     */
    public function getInitJson(): string
    {
        $kind = $this->getComponentKind();
        if ($kind === self::KIND_NONE) {
            return '';
        }

        return $this->json->serialize([
            '*' => [
                'Magento_Ui/js/core/app' => [
                    'components' => [
                        'stockVisualizer' => $this->componentConfig($kind),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Component config plus initial observable seeds for the given strategy.
     *
     * @param string $kind
     * @return array<string, mixed>
     */
    private function componentConfig(string $kind): array
    {
        $product = $this->getProduct();
        $sku = $product ? (string) $product->getSku() : '';
        $onDemand = $this->isOnDemand();
        $perSource = $this->isPerSource();
        $config = [
            'component' => 'Magento_InventoryStockVisualizer/js/view/availability',
            'kind' => $kind,
            'mode' => $this->config->getMode(),
            'sku' => $sku,
            'levelDisplay' => $this->isLevelMode(),
            'configVersion' => $this->config->getVersion(),
        ];

        if ($kind === self::KIND_QUANTITY) {
            $config += [
                'scope' => $this->config->getScope(),
                'ajaxUrl' => $this->getUrl('inventory_stockviz/product/view'),
                'perSource' => $perSource,
                'hideEmptySources' => $this->config->hideEmptySources(),
                'showSourceLabels' => $this->showSourceLabels(),
                'sourceScaffold' => $perSource ? array_values($this->getScaffoldSources()) : [],
                'sourcesVisible' => $perSource && !$onDemand,
                'loading' => !$onDemand,
                'showPrompt' => false,
                'showCta' => $onDemand,
            ];
        } elseif ($kind === self::KIND_VARIANT) {
            $config += [
                'ajaxUrl' => $this->getUrl('inventory_stockviz/product/view'),
                'perSource' => $perSource,
                'hideEmptySources' => $this->config->hideEmptySources(),
                'showSourceLabels' => $this->showSourceLabels(),
                'sourceScaffold' => $perSource ? array_values($this->getScaffoldSources()) : [],
                'loading' => false,
                'showPrompt' => true,
                'showCta' => false,
            ];
        } elseif ($kind === self::KIND_CHILDREN) {
            $config += [
                'ajaxUrl' => $this->getUrl('inventory_stockviz/product/children'),
                'childScaffold' => $this->getChildScaffold(),
                'childrenVisible' => !$onDemand,
                'loading' => false,
                'showPrompt' => false,
                'showCta' => $onDemand,
            ];
        } else {
            $config += [
                'ajaxUrl' => $this->getUrl('inventory_stockviz/product/bundleMax'),
                'loading' => !$onDemand,
                'showPrompt' => false,
                'showCta' => $onDemand,
            ];
        }

        $level = $this->resolveStatusLevel($kind);
        $config['statusLevel'] = $level;
        $config['statusWord'] = $this->levelLabel($level);

        return $config;
    }

    /**
     * Configured composite display mode for the current product type, or '' for stockable types.
     *
     * @return string
     */
    public function getCompositeMode(): string
    {
        $product = $this->getProduct();
        if ($product === null) {
            return '';
        }
        switch ($product->getTypeId()) {
            case 'configurable':
                return $this->config->getConfigurableMode();
            case 'bundle':
                return $this->config->getBundleMode();
            case 'grouped':
                return $this->config->getGroupedMode();
            default:
                return '';
        }
    }

    /**
     * Whether the configurable variant-driven mode is active.
     *
     * @return bool
     */
    public function isVariantMode(): bool
    {
        return $this->getCompositeMode() === Config::COMPOSITE_MODE_VARIANT;
    }

    /**
     * Whether the bundle sellable-count mode is active.
     *
     * @return bool
     */
    public function isBundleMaxMode(): bool
    {
        return $this->getCompositeMode() === Config::COMPOSITE_MODE_MAX;
    }

    /**
     * CSS modifier class for a level.
     *
     * @param string $level
     * @return string
     */
    public function levelClass(string $level): string
    {
        return 'level-' . $level;
    }

    /**
     * Availability-bar fill percentage for a level (level mode).
     *
     * @param string $level
     * @return int
     */
    public function levelFill(string $level): int
    {
        return $this->availabilityData->fillPercent($level);
    }

    /**
     * Human-readable label for a level.
     *
     * @param string $level
     * @return string
     */
    public function levelLabel(string $level): string
    {
        switch ($level) {
            case Level::HIGH:
                return (string) __('In stock');
            case Level::MEDIUM:
                return (string) __('Limited availability');
            case Level::LOW:
                return (string) __('Low stock');
            default:
                return (string) __('Out of stock');
        }
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(): array
    {
        if (!$this->isEnabled() || !$this->isLevelMode()) {
            return [];
        }

        return [CacheTag::CACHE_TAG . '_' . (int) $this->getProduct()->getId()];
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        return $this->isEnabled() ? parent::_toHtml() : '';
    }

    /**
     * Current product from the registry, if any.
     *
     * @return ProductInterface|null
     */
    private function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Stock id for the current website, or null when it cannot be resolved.
     *
     * @return int|null
     */
    private function getStockId(): ?int
    {
        if (!$this->stockResolved) {
            $this->stockResolved = true;
            $this->stockId = $this->availabilityData->resolveStockId();
        }

        return $this->stockId;
    }

    /**
     * Effective display config (per-product override merged over store defaults).
     *
     * @return DisplayConfig
     */
    private function getDisplayConfig(): DisplayConfig
    {
        if ($this->displayConfig === null) {
            $this->displayConfig = $this->availabilityData->displayConfig($this->getProduct());
        }

        return $this->displayConfig;
    }

    /**
     * Availability quantities for the current product/stock (level mode), memoized per render.
     *
     * @return \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface
     */
    private function getView(): \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface
    {
        if ($this->view === null) {
            $product = $this->getProduct();
            $this->view = $this->availabilityData->view(
                (string) $product->getSku(),
                (int) $this->getStockId(),
                $product ? $product->getTypeId() : null
            );
        }

        return $this->view;
    }
}
