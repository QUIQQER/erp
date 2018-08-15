define('package/quiqqer/erp/bin/backend/utils/ErpMenuSettings', function () {
    "use strict";

    return function () {
        return new Promise(function (resolve) {
            require(['Menu'], function (Menu) {
                var Bar = Menu.getChildren();

                if (!Bar) {
                    return resolve();
                }

                var Settings = Bar.getChildren('settings');

                if (!Settings) {
                    return resolve();
                }

                var Erp = Settings.getChildren('ERP/');

                if (Erp) {
                    Erp.click();
                }

                resolve();
            });
        });
    };
});