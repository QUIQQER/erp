define('package/quiqqer/erp/bin/controls/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel'

], function (QUI, QUIPanel) {
    "use strict";

    return new Class({
        Extends: QUIPanel,
        Type: 'package/quiqqer/erp/bin/controls/Panel',

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: 'Shop',
                icon: 'fa fa-shopping-cart'
            });
        },

        $onCreate: function () {

        }
    });
});