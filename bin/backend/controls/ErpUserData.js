/**
 * @module package/quiqqer/erp/bin/backend/controls/ErpUserData
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/ErpUserData', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/ErpUserData',

        Binds: [
            'checkAddressVatField'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$AddressSelect = null;
            this.$ChUID         = null;
            this.$EuVatId       = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * @event: on import
         */
        $onImport: function () {
            var self = this;

            this.$AddressSelect = this.getElm().getElement('[name="quiqqer.erp.address"]');
            this.$ChUID         = this.getElm().getElement('[name="quiqqer.erp.chUID"]');
            this.$EuVatId       = this.getElm().getElement('[name="quiqqer.erp.euVatId"]');

            this.$AddressSelect.addEvent('change', this.checkAddressVatField);
            this.$AddressSelect.addEvent('load', function () {
                var Instance = QUI.Controls.getById(self.$AddressSelect.get('data-quiid'));
                Instance.addEvent('load', self.checkAddressVatField);
            });

            this.checkAddressVatField();
        },

        /**
         * which vat field should be shown
         */
        checkAddressVatField: function () {
            var value  = this.$AddressSelect.value;
            var Option = this.$AddressSelect.getElement('[value="' + value + '"]');

            this.$EuVatId.getParent('tr').setStyle('display', '');
            this.$ChUID.getParent('tr').setStyle('display', 'none');
            
            if (!Option) {
                return;
            }

            var address = Option.innerText;
            address     = address.trim();
            address     = address.split(',');

            var country = address.pop();
            country     = country.trim();

            if (country === 'CH') {
                this.$EuVatId.getParent('tr').setStyle('display', 'none');
                this.$ChUID.getParent('tr').setStyle('display', '');
            } else {
                this.$EuVatId.getParent('tr').setStyle('display', '');
                this.$ChUID.getParent('tr').setStyle('display', 'none');
            }
        }

    });
});
