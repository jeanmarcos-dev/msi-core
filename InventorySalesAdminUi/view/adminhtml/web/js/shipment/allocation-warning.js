/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
define([
    'ko',
    'underscore',
    'uiElement',
    'mage/translate'
], function (ko, _, Element, $t) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'Magento_InventorySalesAdminUi/shipment/allocation-warning',
            selectedSource: '',
            items: [],
            imports: {
                selectedSource: '${ $.parentName }.sourceCode:value',
                items: '${ $.provider }:data.items'
            },
            tracks: {
                selectedSource: true,
                items: true
            }
        },

        initialize: function () {
            this._super();
            this.warnings = ko.pureComputed(this.getWarnings, this);

            return this;
        },

        getWarnings: function () {
            var selectedSource = this.selectedSource;

            if (!selectedSource) {
                return [];
            }

            return _.compact(_.map(this.items || [], function (item) {
                var allocated = item.allocatedSources || [];

                if (!allocated.length || _.findWhere(allocated, {sourceCode: selectedSource})) {
                    return null;
                }

                return $t('%1 is reserved at %2. Shipping from this source takes the stock from it and releases that reservation.')
                    .replace('%1', item.sku)
                    .replace('%2', _.map(allocated, function (source) {
                        return source.sourceName + ' (' + source.qty + ')';
                    }).join(', '));
            }));
        }
    });
});
