/**
 * @module package/quiqqer/erp/bin/frontend/controls/frontendUsers/VatType
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/frontend/controls/frontendUsers/VatType', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/frontend/controls/frontendUsers/VatType',

        Binds: [
            '$onImport',
            '$onCountryChange'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Country = null;
            this.$Vat     = null;
            this.$ChUID   = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var Parent = this.getElm().getParent(
                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData"]'
            );

            if (!Parent) {
                return;
            }

            this.$Country = Parent.getElement('[name="country"]');
            this.$Vat     = Parent.getElement('[name="vatId"]');
            this.$ChUID   = Parent.getElement('[name="chUID"]');

            if (!this.$Country) {
                return;
            }

            if (!this.$Vat) {
                this.$Vat = new Element('div');
            }

            if (!this.$ChUID) {
                this.$ChUID = new Element('div');
            }

            // country edit
            var self    = this;
            var Country = this.$Country;

            if (Country.get('data-qui') && !Country.get('data-quiid')) {
                QUI.parse(this.getElm()).then(function () {
                    QUI.Controls
                       .getById(Country.get('data-quiid'))
                       .addEvent('onCountryChange', self.$onCountryChange);
                });
            } else if (Country.get('data-quiid')) {
                QUI.Controls
                   .getById(Country.get('data-quiid'))
                   .addEvent('onCountryChange', self.$onCountryChange);
            } else {
                Country.addEvent('change', self.$onCountryChange);
            }

            this.$onCountryChange();
        },

        /**
         * event: on country change
         */
        $onCountryChange: function () {
            var country = this.$Country.value;

            if (country === 'CH') {
                this.$Vat.getParent('label').setStyle('display', 'none');
                this.$ChUID.getParent('label').setStyle('display', null);
            } else {
                this.$Vat.getParent('label').setStyle('display', null);
                this.$ChUID.getParent('label').setStyle('display', 'none');
            }
        }
    });
});
