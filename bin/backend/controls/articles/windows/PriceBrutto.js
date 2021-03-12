/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/windows/PriceBrutto
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/articles/windows/PriceBrutto', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceBrutto.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceBrutto.css'

], function (QUI, QUIConfirm, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/windows/PriceBrutto',

        options: {
            value: false,
            vat  : false    // false = shop default vat
        },

        Binds: [
            '$onOpen'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon     : 'fa fa-calculator',
                title    : QUILocale.get(lg, 'control.window.price.brutto.title'),
                maxHeight: 400,
                maxWidth : 600
            });

            this.addEvents({
                onOpen: this.$onOpen
            });

            // admin format
            this.$Formatter = QUILocale.getNumberFormatter({
                //style                : 'currency',
                //currency             : 'EUR',
                minimumFractionDigits: 8
            });
        },

        /**
         * Return the domnode element
         *
         * @return {Element}
         */
        $onOpen: function () {
            var self    = this,
                Content = this.getContent();

            Content.set('html', Mustache.render(template, {
                title      : QUILocale.get(lg, 'control.window.price.brutto.label'),
                description: QUILocale.get(lg, 'control.window.price.brutto.description')
            }));

            Content.addClass('erp-price-brutto-window');
            Content.getElement('input').placeholder = this.$Formatter.format(1000);

            Content.getElement('form').addEvent('submit', function (event) {
                event.stop();
                self.submit();
            });

            if (this.getAttribute('value')) {
                Content.getElement('input').value = this.getAttribute('value');
            }

            this.getContent().getElement('input').focus();
        },

        /**
         * submit the window
         */
        submit: function () {
            var self = this;

            this.Loader.show();

            QUIAjax.get('package_quiqqer_erp_ajax_calcNettoPrice', function (price) {
                self.fireEvent('submit', [self, price]);
                self.close();
            }, {
                'package': 'quiqqer/erp',
                price    : this.getContent().getElement('input').value,
                vat      : this.getAttribute('vat')
            });
        }
    });
});
