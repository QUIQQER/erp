/**
 * @module package/quiqqer/erp/bin/backend/utils/Money
 */
define('package/quiqqer/erp/bin/backend/utils/Money', [
    'Locale'
], function (QUILocale) {
    "use strict";

    return {
        /**
         * Validate the price and return a validated price
         *
         * @param {String|Number} value
         * @return {Promise}
         */
        validatePrice: function (value) {
            return new Promise(function (resolve) {
                require(['Ajax'], function (QUIAjax) {
                    QUIAjax.get('package_quiqqer_erp_ajax_money_validatePrice', resolve, {
                        'package': 'quiqqer/erp',
                        value    : value
                    });
                });
            });
        },

        /**
         * Format the price for the backend
         *
         * @param value
         * @return {Number|String}
         */
        formatPrice: function (value) {
            if (value === '' || !value || value === 'false') {
                return '';
            }

            var Formatter = QUILocale.getNumberFormatter({
                minimumFractionDigits: 8
            });

            var groupingSeparator = QUILocale.getGroupingSeparator();
            var decimalSeparator  = QUILocale.getDecimalSeparator();

            var foundGroupSeparator   = typeOf(value) === 'string' && value.indexOf(groupingSeparator) >= 0;
            var foundDecimalSeparator = typeOf(value) === 'string' && value.indexOf(decimalSeparator) >= 0;

            if ((foundGroupSeparator || foundDecimalSeparator) && !(foundGroupSeparator && !foundDecimalSeparator)) {
                return value;
            }

            return Formatter.format(parseFloat(value));
        }
    };
});