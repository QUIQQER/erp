define('package/quiqqer/erp/bin/backend/utils/Money', [
    'Locale'
], function (QUILocale) {
    "use strict";

    var defaultCurrency = null;

    return {
        /**
         * Preserve number types and identify the regional format of text input.
         */
        serializeInput: function (value) {
            if ((typeof value !== 'string' && typeof value !== 'number') ||
                (typeof value === 'number' && !Number.isFinite(value))) {
                throw new TypeError('Invalid price input');
            }

            return JSON.stringify({
                value: value,
                locale: QUILocale.getNumberFormatter().resolvedOptions().locale
            });
        },

        /**
         * Validate the price and return a validated price
         *
         * @param {String|Number} value
         * @return {Promise}
         */
        validatePrice: function (value) {
            return new Promise((resolve, reject) => {
                const input = this.serializeInput(value);
                require(['Ajax'], function (QUIAjax) {
                    QUIAjax.get('package_quiqqer_erp_ajax_money_validatePrice', resolve, {
                        'package': 'quiqqer/erp',
                        value    : input,
                        onError  : reject
                    });
                });
            });
        },

        /**
         * Normalize a user-entered discount before storing it as an amount or percentage.
         */
        validateDiscount: function (value) {
            const percentage = typeof value === 'string' && /%\s*$/.test(value);
            const input = percentage ? value.replace(/%\s*$/, '').trim() : value;

            return this.validatePrice(input).then(amount => percentage ? amount + '%' : amount);
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
        },

        /**
         * Return the default currency
         *
         * @return {Promise}
         */
        getCurrency: function () {
            if (defaultCurrency !== null) {
                return Promise.resolve(defaultCurrency);
            }

            return new Promise(function (resolve) {
                require(['Ajax'], function (QUIAjax) {
                    QUIAjax.get('package_quiqqer_erp_ajax_money_getCurrency', function (result) {
                        defaultCurrency = result;
                        resolve(result);
                    }, {
                        'package': 'quiqqer/erp'
                    });
                });
            });
        }
    };
});
