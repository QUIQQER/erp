/**
 * @module package/quiqqer/erp/bin/backend/controls/ErpUserData
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/ErpUserData', [

    'qui/QUI',
    'qui/controls/Control',

    'css!package/quiqqer/erp/bin/backend/controls/ErpUserData.css'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/ErpUserData',

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * @event: on import
         */
        $onImport: function () {
            var self  = this;
            var Panel = QUI.Controls.getById(
                self.getElm().getParent('.qui-panel').get('data-quiid')
            );

            this.getElm().addEvent('click', function (e) {
                e.stop();

                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel',
                    'utils/Panels'
                ], function (CustomerPanel, Utils) {
                    Utils.openPanelInTasks(
                        new CustomerPanel({
                            userId: Panel.getUser().getId()
                        })
                    );
                });
            });

            this.getElm().set('disabled', false);
        }
    });
});
