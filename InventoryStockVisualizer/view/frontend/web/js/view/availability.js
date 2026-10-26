/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

define([
    'uiElement',
    'jquery',
    'knockoutjs/knockout',
    'mage/translate'
], function (Element, $, ko, $t) {
    'use strict';

    var RECOMPUTE_DELAY = 60;

    var LEVEL_FILL = { high: 100, medium: 60, low: 30, out: 0 };

    return Element.extend({
        defaults: {
            kind: 'quantity',
            mode: 'instant',
            scope: 'aggregate',
            perSource: false,
            hideEmptySources: true,
            showSourceLabels: false,
            ajaxUrl: '',
            sku: '',
            configVersion: '',
            sourceScaffold: [],
            childScaffold: [],
            statusLevel: 'out',
            statusWord: '',
            loading: false,
            count: '',
            showPrompt: false,
            showCta: false,
            showEmptyNote: false,
            sourcesVisible: false,
            childrenVisible: false,
            setsText: '',
            sourceRows: [],
            variantRows: [],
            childRows: []
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this._boot();

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe([
                'statusLevel', 'statusWord', 'loading', 'count',
                'showPrompt', 'showCta', 'showEmptyNote', 'sourcesVisible',
                'childrenVisible', 'setsText',
                'sourceRows', 'variantRows', 'childRows'
            ]);

            this.statusClass = ko.pureComputed(function () {
                return 'level-' + this.statusLevel();
            }, this);

            this.showCount = ko.pureComputed(function () {
                return this.loading() || this.count() !== '';
            }, this);

            this.flush = ko.pureComputed(function () {
                switch (this.kind) {
                    case 'variant':
                        return !this.showPrompt() && this.variantRows().length === 0;
                    case 'bundleMax':
                        return !this.showPrompt();
                    case 'children':
                        return !this.childrenVisible() || this.childRows().length === 0;
                    default:
                        return this.perSource ? !this.sourcesVisible() : true;
                }
            }, this);

            return this;
        },

        /**
         * Route the boot sequence to the strategy the panel was configured with.
         *
         * @private
         */
        _boot: function () {
            switch (this.kind) {
                case 'variant':
                    this._bootVariant();
                    break;
                case 'bundleMax':
                    this._bootBundle();
                    break;
                case 'children':
                    this._bootChildren();
                    break;
                default:
                    this._bootQuantity();
            }
        },

        /**
         * Call-to-action handler (on-demand modes). Shared across strategies: the button is
         * only rendered when a fetch is deferred, so activating it always starts one.
         */
        activate: function () {
            this.showCta(false);

            switch (this.kind) {
                case 'variant':
                    this.loading(true);
                    this._fetchVariant(this.pendingId);
                    break;
                case 'bundleMax':
                    this.loading(true);
                    this._fetchBundle(this.pendingSelections);
                    break;
                case 'children':
                    this.childrenVisible(true);
                    this._fetchChildren();
                    break;
                default:
                    this.sourcesVisible(true);
                    this.loading(true);
                    this._fetchQuantity();
            }
        },

        /**
         * Quantity strategy (simple/virtual/downloadable): a single SKU with an exact salable
         * quantity and an optional per-source breakdown. In instant mode it fetches on mount;
         * in on-demand mode it waits for the call-to-action.
         *
         * @private
         */
        _bootQuantity: function () {
            if (this.perSource) {
                this.sourceRows(this._scaffoldRows());
            }
            if (this.mode === 'instant') {
                this.loading(true);
                this._fetchQuantity();
            }
        },

        /**
         * @private
         */
        _fetchQuantity: function () {
            var self = this;

            $.ajax({
                url: this.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                cache: true,
                data: { sku: this.sku, _cv: this.configVersion }
            }).done(function (response) {
                self._fillQuantity(response && response.data ? response.data : null);
            }).fail(function () {
                self._fillQuantity(null);
            });
        },

        /**
         * Reconcile the cached status pill with the live salable quantity and reveal the
         * number. On a failed fetch the server-rendered status is left untouched.
         *
         * @param {Object|null} data
         * @private
         */
        _fillQuantity: function (data) {
            this.loading(false);

            if (!data) {
                return;
            }

            var salable = data.qty > 0;

            this.statusLevel(salable ? 'high' : 'out');
            this.statusWord(salable ? $t('In stock') : $t('Out of stock'));
            this.count(salable ? this._formatQty(data.qty) : '');

            if (this.perSource) {
                this._fillSources(this.sourceRows(), data.sources || {});
            }
        },

        /**
         * Variant strategy (configurable): show the exact availability of the child the
         * customer selects. Nothing is fetched until a full option combination resolves.
         *
         * @private
         */
        _bootVariant: function () {
            var self = this;

            this._form().on('change', '.super-attribute-select, .swatch-input', function () {
                self._onVariantChange();
            });
        },

        /**
         * React to a variant selection. Instant mode fetches immediately; on-demand reveals the
         * call-to-action so the fetch is deferred to an explicit click, and falls back to the
         * prompt while the selection is incomplete.
         *
         * @private
         */
        _onVariantChange: function () {
            var productId = this._resolveSelectedProductId();

            if (!productId) {
                this._promptInteractive();

                return;
            }
            this.showPrompt(false);

            if (this.mode === 'instant') {
                this.loading(true);
                this._fetchVariant(productId);

                return;
            }
            this.pendingId = productId;
            this._deferInteractive();
        },

        /**
         * @param {Number} productId
         * @private
         */
        _fetchVariant: function (productId) {
            var self = this;

            $.ajax({
                url: this.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                cache: true,
                data: { product_id: productId, level: this.levelDisplay ? 1 : 0, _cv: this.configVersion }
            }).done(function (response) {
                self._fillVariant(response && response.data ? response.data : null);
            }).fail(function () {
                self._promptInteractive();
            });
        },

        /**
         * @param {Object|null} data
         * @private
         */
        _fillVariant: function (data) {
            this.loading(false);

            if (!data) {
                this._promptInteractive();

                return;
            }
            this.showPrompt(false);

            if (typeof data.level !== 'undefined') {
                var level = data.level || 'out';

                this.statusLevel(level);
                this.statusWord(this._levelLabel(level));
                this.count('');
                this.variantRows(this._buildLevelRows(data.sources || {}));

                return;
            }

            if (typeof data.qty === 'undefined') {
                this._promptInteractive();

                return;
            }

            var salable = data.qty > 0;

            this.statusLevel(salable ? 'high' : 'out');
            this.statusWord(salable ? $t('In stock') : $t('Out of stock'));
            this.count(salable ? this._formatQty(data.qty) : '');
            this.variantRows(this._buildVariantRows(salable ? (data.sources || {}) : {}));
        },

        /**
         * The child product id for the fully selected option combination, or null. Both native
         * widgets already resolve it: the dropdown configurable exposes the resolved child in
         * `simpleProduct`, and the swatch renderer's `getProductId()` returns an id only when the
         * selection narrows to a single product (its own price-calculation intersection). The
         * panel never reconstructs the selection from the DOM.
         *
         * @return {Number|null}
         * @private
         */
        _resolveSelectedProductId: function () {
            var $form = this._form(),
                configurable = $form.data('mageConfigurable');

            if (configurable) {
                return this._toId(configurable.simpleProduct);
            }

            var swatch = this._swatchRenderer();

            if (swatch && typeof swatch.getProductId === 'function') {
                return this._toId(swatch.getProductId());
            }

            return null;
        },

        /**
         * The swatch renderer widget instance, if the configurable renders as swatches. The
         * jQuery UI bridge stores it under the widget full name ('mage-SwatchRenderer'); the
         * camel-cased key is checked too for other versions.
         *
         * @return {Object|null}
         * @private
         */
        _swatchRenderer: function () {
            var $el = $('[data-role=swatch-options]');

            return $el.data('mageSwatchRenderer') || $el.data('mage-SwatchRenderer') || null;
        },

        /**
         * Coerce a native id value to a positive integer, or null.
         *
         * @param {*} value
         * @return {Number|null}
         * @private
         */
        _toId: function (value) {
            var id = parseInt(value, 10);

            return id > 0 ? id : null;
        },

        /**
         * Bundle strategy (max sellable): compute how many of the current selection can be
         * ordered. Reads the live selection from the native priceBundle option config and
         * recomputes as the customer changes options or quantities.
         *
         * @private
         */
        _bootBundle: function () {
            var self = this;

            this._form().on('updateProductSummary', function (event, data) {
                self.optionConfig = data && data.config ? data.config : self.optionConfig;
                self._onBundleChange();
            });

            this._onBundleChange();
        },

        /**
         * React to a bundle selection change. Instant mode recomputes immediately; on-demand
         * reveals the call-to-action so the compute is deferred to an explicit click.
         *
         * @private
         */
        _onBundleChange: function () {
            var self = this;

            clearTimeout(this.recomputeTimer);
            this.recomputeTimer = setTimeout(function () {
                var selections = self._collectSelections();

                if ($.isEmptyObject(selections)) {
                    self._promptInteractive();

                    return;
                }
                self.showPrompt(false);

                if (self.mode === 'instant') {
                    self.loading(true);
                    self._fetchBundle(selections);

                    return;
                }
                self.pendingSelections = selections;
                self._deferInteractive();
            }, RECOMPUTE_DELAY);
        },

        /**
         * @param {Object} selections
         * @private
         */
        _fetchBundle: function (selections) {
            var self = this;

            $.ajax({
                url: this.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                cache: true,
                data: {
                    sku: this.sku,
                    selections: JSON.stringify(selections),
                    _cv: this.configVersion
                }
            }).done(function (response) {
                self._fillBundle(response && response.data ? response.data : null);
            }).fail(function () {
                self._promptInteractive();
            });
        },

        /**
         * @param {Object|null} data
         * @private
         */
        _fillBundle: function (data) {
            this.loading(false);

            if (!data) {
                this._promptInteractive();

                return;
            }
            this.showPrompt(false);

            if (this.levelDisplay) {
                var level = data.level || 'out';

                this.statusLevel(level);
                this.statusWord(this._levelLabel(level));
                this.count('');

                return;
            }

            if (data.max === null || typeof data.max === 'undefined') {
                this._promptInteractive();

                return;
            }

            var salable = data.max > 0;

            this.statusLevel(salable ? 'high' : 'out');
            this.statusWord(salable ? $t('In stock') : $t('Out of stock'));
            this.count(salable ? $t('up to %1').replace('%1', data.max) : '');
        },

        /**
         * The live bundle {selectionId: qty} map from the native priceBundle option config,
         * including customer-edited quantities and the required options Magento auto-selects.
         *
         * @return {Object}
         * @private
         */
        _collectSelections: function () {
            var config = this._optionConfig(),
                selections = {};

            if (!config || !config.selected) {
                return selections;
            }

            $.each(config.selected, function (optionId, selectionIds) {
                if (!selectionIds || !selectionIds.length) {
                    return;
                }
                var option = config.options && config.options[optionId] ? config.options[optionId] : null;

                $.each(selectionIds, function (index, selectionId) {
                    if (selectionId === null || selectionId === undefined || selectionId === '') {
                        return;
                    }
                    var selection = option && option.selections ? option.selections[selectionId] : null,
                        qty = selection ? parseFloat(selection.qty) : 1;

                    selections[selectionId] = qty > 0 ? qty : 1;
                });
            });

            return selections;
        },

        /**
         * @return {Object|null}
         * @private
         */
        _optionConfig: function () {
            if (this.optionConfig) {
                return this.optionConfig;
            }
            var priceBundle = this._form().data('magePriceBundle');

            return priceBundle && priceBundle.options ? priceBundle.options.optionConfig : null;
        },

        /**
         * Return the interactive panel to its pre-selection prompt.
         *
         * @private
         */
        _promptInteractive: function () {
            this.loading(false);
            this.count('');
            this.variantRows([]);
            this.showCta(false);
            this.showPrompt(true);
        },

        /**
         * Defer the fetch behind the call-to-action (on-demand): a selection exists but is not
         * fetched until the customer clicks, so any stale result is cleared while the button shows.
         *
         * @private
         */
        _deferInteractive: function () {
            this.loading(false);
            this.count('');
            this.variantRows([]);
            this.showPrompt(false);
            this.showCta(true);
        },

        /**
         * Human-readable label for a level.
         *
         * @param {String} level
         * @return {String}
         * @private
         */
        _levelLabel: function (level) {
            switch (level) {
                case 'high':
                    return $t('In stock');
                case 'medium':
                    return $t('Limited availability');
                case 'low':
                    return $t('Low stock');
                default:
                    return $t('Out of stock');
            }
        },

        /**
         * Availability-bar fill percentage for a level.
         *
         * @param {String} level
         * @return {Number}
         * @private
         */
        _levelFill: function (level) {
            return LEVEL_FILL[level] || 0;
        },

        /**
         * Build the selected variant's per-source level rows (level display), honouring hide-empty.
         *
         * @param {Object} sources code => level
         * @return {Array}
         * @private
         */
        _buildLevelRows: function (sources) {
            var self = this,
                rows = [];

            this.sourceScaffold.forEach(function (source) {
                var level = sources[source.code] || 'out';

                if (self.hideEmptySources && level === 'out') {
                    return;
                }
                rows.push({
                    code: source.code,
                    name: source.name,
                    qtyText: self._levelLabel(level),
                    level: level,
                    fill: self._levelFill(level)
                });
            });

            return rows;
        },

        /**
         * Children strategy (composite children/status): fetch the per-child breakdown as a
         * cacheable fragment so the volatile child quantities never sit in the product page.
         * The child labels are known from the structure scaffold; only their stock is fetched.
         *
         * @private
         */
        _bootChildren: function () {
            this.childRows(this._scaffoldChildRows());

            if (this.mode === 'instant') {
                this.childrenVisible(true);
                this._fetchChildren();
            }
        },

        /**
         * @private
         */
        _fetchChildren: function () {
            var self = this;

            $.ajax({
                url: this.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                cache: true,
                data: { sku: this.sku, _cv: this.configVersion }
            }).done(function (response) {
                self._fillChildren(response && response.data ? response.data : null);
            }).fail(function () {
                self._fillChildren(null);
            });
        },

        /**
         * Fill the child rows and the grouped-sets line, and reconcile the aggregate status
         * pill from the fetched salability (the server-rendered pill is coarse and may be stale).
         *
         * @param {Object|null} data
         * @private
         */
        _fillChildren: function (data) {
            if (!data) {
                return;
            }

            this.statusLevel(data.salable ? 'high' : 'out');
            this.statusWord(data.salable ? $t('In stock') : $t('Out of stock'));

            var self = this,
                bySku = {};

            $.each(data.children || [], function (index, child) {
                bySku[child.sku] = child;
            });

            this.childRows().forEach(function (row) {
                var child = bySku[row.sku];

                row.loading(false);
                if (!child) {
                    return;
                }
                row.salable(child.salable);
                if (self.levelDisplay) {
                    row.value(self._levelLabel(child.level));
                    row.level(child.level);
                    row.fill(self._levelFill(child.level));
                } else {
                    row.value(child.salable ? self._formatQty(child.qty) : $t('Out of stock'));
                }
            });

            this.setsText(this._setsText(data.sets));
        },

        /**
         * Human-readable grouped-sets line for the fetched maximum, or '' when not applicable.
         *
         * @param {Number|null|undefined} sets
         * @return {String}
         * @private
         */
        _setsText: function (sets) {
            if (sets === null || typeof sets === 'undefined') {
                return '';
            }
            if (sets <= 0) {
                return $t('Not enough stock to assemble a complete set.');
            }
            if (this.levelDisplay) {
                return $t('Complete sets available.');
            }

            return $t('You can assemble up to %1 complete set(s).').replace('%1', sets);
        },

        /**
         * Build the child scaffold rows (labels known from structure, stock arrives over AJAX).
         *
         * @return {Array}
         * @private
         */
        _scaffoldChildRows: function () {
            return this.childScaffold.map(function (child) {
                return {
                    sku: child.sku,
                    label: child.label,
                    value: ko.observable(''),
                    salable: ko.observable(true),
                    level: ko.observable('out'),
                    fill: ko.observable(0),
                    loading: ko.observable(true)
                };
            });
        },

        /**
         * Build the per-source scaffold rows (labels only, numbers arrive over AJAX).
         *
         * @return {Array}
         * @private
         */
        _scaffoldRows: function () {
            return this.sourceScaffold.map(function (source) {
                return {
                    code: source.code,
                    name: source.name,
                    qty: ko.observable(''),
                    fill: ko.observable(0),
                    collapsed: ko.observable(false),
                    loading: ko.observable(true)
                };
            });
        },

        /**
         * Fill per-source value cells in place and size each meter as a share of the total.
         *
         * @param {Array} rows
         * @param {Object} sources code => quantity
         * @private
         */
        _fillSources: function (rows, sources) {
            var self = this,
                total = 0,
                visible = 0;

            $.each(sources, function (code, qty) {
                total += qty > 0 ? qty : 0;
            });

            rows.forEach(function (row) {
                var qty = typeof sources[row.code] !== 'undefined' ? sources[row.code] : 0,
                    width = total > 0 && qty > 0 ? Math.round(qty / total * 100) : 0;

                row.qty(self._formatQty(qty));
                row.fill(width);
                row.loading(false);

                if (self.hideEmptySources && qty <= 0) {
                    row.collapsed(true);
                } else {
                    row.collapsed(false);
                    visible++;
                }
            });

            this.showEmptyNote(visible === 0);
        },

        /**
         * Build the selected variant's per-source rows, honouring hide-empty.
         *
         * @param {Object} sources code => quantity
         * @return {Array}
         * @private
         */
        _buildVariantRows: function (sources) {
            var self = this,
                rows = [],
                total = 0;

            $.each(sources, function (code, qty) {
                total += qty > 0 ? qty : 0;
            });

            this.sourceScaffold.forEach(function (source) {
                var qty = sources[source.code] || 0;

                if (self.hideEmptySources && qty <= 0) {
                    return;
                }
                rows.push({
                    code: source.code,
                    name: source.name,
                    qtyText: self._formatQty(qty),
                    level: '',
                    fill: total > 0 && qty > 0 ? Math.round(qty / total * 100) : 0
                });
            });

            return rows;
        },

        /**
         * The add-to-cart form that carries the type-specific client widgets.
         *
         * @return {jQuery}
         * @private
         */
        _form: function () {
            if (!this.$form || !this.$form.length) {
                this.$form = $('#product_addtocart_form');
            }

            return this.$form;
        },

        /**
         * Format a quantity for display.
         *
         * @param {Number} qty
         * @return {String}
         * @private
         */
        _formatQty: function (qty) {
            return (Math.round(qty * 100) / 100).toString();
        }
    });
});
