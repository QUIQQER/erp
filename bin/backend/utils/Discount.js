/**
 * @module package/quiqqer/erp/bin/backend/utils/Discount
 */
define('package/quiqqer/erp/bin/backend/utils/Discount', function () {
    "use strict";

    return {
        /**
         * Unserialize a discount
         *
         * @param {String|Object} discount
         * @return {Object|null}
         */
        unserialize: function (discount) {

            if (typeOf(discount) === 'number') {
                return {
                    value: discount,
                    type : 2
                };
            }

            if (!discount) {
                return null;
            }

            if (discount === '') {
                return null;
            }

            if (typeOf(discount) === 'object') {
                if ("value" in discount && "type" in discount) {
                    return discount;
                }

                return null;
            }

            if (discount.toString().match('{')) {
                try {
                    return this.unserialize(JSON.decode(discount));
                } catch (e) {
                    console.error(e);
                }
            }

            if (discount.toString().match('%')) {
                return {
                    value: discount,
                    type : 1
                };
            }

            return null;
        },

        /**
         * Return the discount as string representation
         *
         * @param {Object} discount
         * @return {String}
         */
        parseToString: function (discount) {
            if (!discount) {
                return null;
            }

            if (!("value" in discount) || !("type" in discount)) {
                return '';
            }

            if (parseInt(discount.type) === 1) {
                return discount.value + '%';
            }

            return discount.value;
        }
    };
});