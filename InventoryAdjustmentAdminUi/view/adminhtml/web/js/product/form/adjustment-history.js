/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
define([
    'Magento_Ui/js/form/components/fieldset'
], function (Fieldset) {
    'use strict';

    return Fieldset.extend({
        defaults: {
            listens: {
                opened: 'renderListing'
            },
            modules: {
                listing: '${ $.name }.adjustment_history_listing'
            }
        },

        renderListing: function (opened) {
            if (opened && this.listing()) {
                this.listing().render();
            }
        }
    });
});
