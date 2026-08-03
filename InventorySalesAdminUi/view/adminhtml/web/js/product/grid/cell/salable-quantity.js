/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
define([
    'ko',
    'Magento_Ui/js/grid/columns/column'
], function (ko, Column) {
    'use strict'; //eslint-disable-line

    return Column.extend({
        defaults: {
            bodyTmpl: 'Magento_InventorySalesAdminUi/product/grid/cell/salable-quantity.html'
        },

        /**
         * Initialize the per-row expansion state.
         *
         * @returns {Object} Chainable
         */
        initialize: function () {
            this._super();
            this.expandedRows = ko.observable({});

            return this;
        },

        /**
         * Get salable quantity data (stock name and salable qty)
         *
         * @param {Object} record - Record object
         * @returns {Array} Result array
         */
        getSalableQuantityData: function (record) {
            return record[this.index] ? record[this.index] : [];
        },

        /**
         * Check whether the record carries a per-source breakdown to expand.
         *
         * @param {Object} record - Record object
         * @returns {Boolean} Result
         */
        hasSourceBreakdown: function (record) {
            return this.getSalableQuantityData(record).some(function (stock) {
                return Boolean(stock.sources && stock.sources.length);
            });
        },

        /**
         * Check whether the breakdown of the record is currently expanded.
         *
         * @param {Object} record - Record object
         * @returns {Boolean} Result
         */
        isRowExpanded: function (record) {
            return Boolean(this.expandedRows()[record._rowIndex]);
        },

        /**
         * Toggle the breakdown of the record.
         *
         * The event must stop here: the grid row itself listens for clicks to open the product,
         * so letting it bubble would navigate away instead of expanding the breakdown.
         *
         * @param {Object} record - Record object
         * @param {Object} event - Click event
         * @returns {Boolean} Always false, so knockout suppresses the default action
         */
        toggleRow: function (record, event) {
            var expanded = Object.assign({}, this.expandedRows());

            expanded[record._rowIndex] = !expanded[record._rowIndex];
            this.expandedRows(expanded);

            if (event) {
                event.stopPropagation();
            }

            return false;
        }
    });
});
