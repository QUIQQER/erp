/**
 * Button which opens the window for the process
 */
define('package/quiqqer/erp/bin/backend/controls/process/ProcessWindowButton', [

    'qui/QUI',
    'qui/controls/buttons/Button',
    'package/quiqqer/erp/bin/backend/controls/process/ProcessWindow',
    'Locale'

], function(QUI, QUIButton, ProcessWindow, QUILocale) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIButton,
        Type: 'package/quiqqer/erp/bin/backend/controls/process/ProcessWindowButton',

        options: {
            globalProcessId: false,
            hash: false
        },

        initialize: function(options) {
            this.setAttributes({
                styles: {
                    'border-left-width': 1,
                    'border-right-width': 1,
                    'float': 'right',
                    width: 40
                }
            });

            this.parent(options);

            this.setAttributes({
                icon: 'fa fa-timeline',
                title: QUILocale.get(lg, 'process.button.title')
            });

            this.addEvents({
                click: () => {
                    new ProcessWindow({
                        globalProcessId: this.getAttribute('globalProcessId'),
                        hash: this.getAttribute('hash')
                    }).open();
                }
            });
        }
    });
});
