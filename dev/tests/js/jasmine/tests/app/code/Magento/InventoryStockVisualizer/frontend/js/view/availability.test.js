/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

/* eslint max-nested-callbacks: 0 */
/* jscs:disable jsDoc*/

define([
    'jquery',
    'Magento_InventoryStockVisualizer/js/view/availability'
], function ($, Availability) {
    'use strict';

    describe('Magento_InventoryStockVisualizer/js/view/availability', function () {
        function variant(levelDisplay) {
            return new Availability({
                kind: 'variant',
                mode: 'instant',
                levelDisplay: levelDisplay,
                ajaxUrl: 'view',
                statusLevel: 'high',
                showPrompt: true
            });
        }

        beforeEach(function () {
            spyOn($, 'ajax').and.returnValue({
                done: function () {
                    return this;
                },
                fail: function () {
                    return this;
                }
            });
        });

        it('asks for a level when the page shows levels', function () {
            variant(true)._fetchVariant(7);

            expect($.ajax.calls.mostRecent().args[0].data.level).toBe(1);
        });

        it('asks for no level when the page shows quantities', function () {
            variant(false)._fetchVariant(7);

            expect($.ajax.calls.mostRecent().args[0].data.level).toBe(0);
        });

        it('shows the level of a variant that hides its quantity on a quantity page', function () {
            var model = variant(false);

            model._fillVariant({level: 'low', salable: true});

            expect(model.statusLevel()).toBe('low');
            expect(model.showPrompt()).toBe(false);
            expect(model.count()).toBe('');
        });

        it('shows the quantity of a variant on a quantity page', function () {
            var model = variant(false);

            model._fillVariant({qty: 4});

            expect(model.statusLevel()).toBe('high');
            expect(model.count()).not.toBe('');
        });
    });
});
