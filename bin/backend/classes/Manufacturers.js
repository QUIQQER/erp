/**
 * Manufacturers JavaScript handler
 *
 * @module package/quiqqer/erp/bin/backend/classes/Manufacturers
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/erp/bin/backend/classes/Manufacturers', [

    'qui/classes/DOM',
    'Ajax'

], function (QUIDOM, QUIAjax) {
    "use strict";

    var pkg = 'quiqqer/erp';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/erp/bin/backend/classes/Manufacturers',

        /**
         * Get IDs of manufacturer groups
         *
         * @return {Promise}
         */
        getManufacturerGroupIds: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_manufacturers_getGroupIds', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Get details of manufacturer groups
         *
         * @return {Promise}
         */
        getManufacturerGroups: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_manufacturers_create_getGroups', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        }
    });
});
