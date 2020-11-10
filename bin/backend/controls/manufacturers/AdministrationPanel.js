/**
 * @module package/quiqqer/erp/bin/backend/controls/manufacturers/AdministrationPanel
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/erp/bin/backend/controls/manufacturers/AdministrationPanel', [

    'qui/controls/desktop/Panel',
    'package/quiqqer/erp/bin/backend/controls/manufacturers/Administration',
    'Locale'

], function (QUIPanel, Administration, QUILocale) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/erp/bin/backend/controls/manufacturers/AdministrationPanel',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get(lg, 'controls.manufacturers.AdministrationPanel.title'),
                icon : 'fa fa-wrench',
                name : 'manufacturers-administration'
            });

            this.addEvents({
                onInject : this.$onInject,
                onResize : function () {
                    if (this.$Administration) {
                        this.$Administration.resize();
                    }

                    this.Loader.hide();
                }.bind(this),
                onDestroy: function () {
                    if (this.$Administration) {
                        this.$Administration.destroy();
                    }
                }.bind(this)
            });
        },

        /**
         * Create the DOMNode element
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            this.$Administration = new Administration({
                events: {
                    onRefreshBegin: function () {
                        self.Loader.show();
                    },

                    onRefreshEnd: function () {
                        self.Loader.hide();
                    }
                }
            });
            this.$Administration.inject(this.getContent());
            this.$Administration.resize();
        }
    });
});
