/**
 * @module package/quiqqer/erp/bin/backend/controls/manufacturers/create/ManufacturerWindow
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/erp/bin/backend/controls/manufacturers/create/ManufacturerWindow', [

    'qui/controls/windows/Popup',
    'Locale',
    'package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer'

], function (QUIPopup, QUILocale, CreateManufacturer) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/erp/bin/backend/controls/manufacturers/create/ManufacturerWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight         : 700,
            maxWidth          : 600,
            buttons           : false,
            backgroundClosable: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon : 'fa fa-id-card',
                title: QUILocale.get(lg, 'controls.manufacturers.create.ManufacturerWindow.title')
            });

            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this;

            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            new CreateManufacturer({
                events: {
                    onLoad: function () {
                        self.Loader.hide();
                    },

                    onCreateManufacturerBegin: function () {
                        self.Loader.show();
                    },

                    onCreateManufacturerEnd: function (Instance, manufacturerId) {
                        self.fireEvent('submit', [self, manufacturerId]);
                        self.close();
                    },

                    onCreateManufacturerError: function () {
                        self.Loader.hide();
                    }
                }
            }).inject(this.getContent());
        }
    });
});
