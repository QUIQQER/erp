/**
 * @module package/quiqqer/erp/bin/backend/utils/Money
 */
define('package/quiqqer/erp/bin/backend/utils/Money', function () {
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
        }
    };
});