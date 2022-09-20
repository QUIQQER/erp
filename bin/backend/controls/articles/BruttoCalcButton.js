/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/BruttoCalcButton
 * @author www.pcsgde (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/articles/BruttoCalcButton', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/products/bin/controls/fields/windows/PriceBrutto',

], function (QUI, QUIButton, PriceBruttoWindow) {
    "use strict";

    return new Class({

        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/BruttoCalcButton',
        Extends: QUIButton,

        Binds: [
            'openBruttoWindow'
        ],

        options: {
            Price: null // input element for the price
        },

        initialize: function (options) {
            this.parent(options);
        },

        create: function () {
            this.$Elm = new Element('button');
            this.$Elm.set('data-quiid', this.getId());
            this.$Elm.set('data-qui', 'package/quiqqer/erp/bin/backend/controls/articles/BruttoCalcButton');
            this.$Elm.set('html', '<span class="fa fa-calculator"></span>');

            this.$Elm.addClass('qui-button');
            this.$Elm.addEvent('click', (e) => {
                e.stop();
                this.openBruttoWindow();
            });

            return this.$Elm;
        },

        openBruttoWindow: function () {
            const Price = this.getAttribute('Price');

            new PriceBruttoWindow({
                events: {
                    onOpen: function (Win) {
                        Win.getContent().set('html', '');
                    },

                    onSubmit: (Win, value) => {
                        Price.value = value;
                    }
                }
            }).open();
        }
    });
});