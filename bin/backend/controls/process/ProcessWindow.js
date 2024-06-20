/**
 * @module package/quiqqer/erp/bin/backend/controls/process/ProcessWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/process/ProcessWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'Locale',
    'Ajax',

    'css!package/quiqqer/erp/bin/backend/controls/process/ProcessWindow.css'

], function(QUI, QUIPopup, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIPopup,
        Type: 'package/quiqqer/erp/bin/backend/controls/process/ProcessWindow',

        Binds: [
            '$onCreate',
            '$onOpen'
        ],

        options: {
            globalProcessId: false,
            hash: false,
            buttons: false
        },

        initialize: function(options) {
            this.setAttributes({
                icon: 'fa fa-timeline',
                title: '',
                maxHeight: 900,
                maxWidth: 750,
            });

            this.parent(options);

            this.$Comments = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onOpen: this.$onOpen
            });
        },

        $onCreate: function() {
            if (!this.getAttribute('globalProcessId') && !this.getAttribute('hash')) {
                this.close();
                return;
            }

            this.Loader.show();
            this.refresh();

            this.getContent().setStyle('padding', 0);
            this.getContent().addClass('quiqqer-erp-process-window');

            new Element('div', {
                'class': 'quiqqer-erp-process-window-header',
                html: '<div class="quiqqer-erp-process-window-header-filter">' +
                    '    <input type="text" name="filter" placeholder="Filter (Nachricht, Type, ID) ...">' +
                    '  </div>'
            }).inject(this.getContent());

            require(['package/quiqqer/erp/bin/backend/controls/Comments'], (Comments) => {
                const CommentContainer = new Element('div', {
                    'class': 'quiqqer-erp-process-window-comments',
                    styles: {
                        padding: 20
                    }
                }).inject(this.getContent());

                const Filter = this.getContent().getElement('[name="filter"]');
                this.$Comments = new Comments().inject(CommentContainer);

                Filter.addEvent('keyup', () => {
                    this.$Comments.filter(Filter.value);
                });

                this.$onOpen();
            });
        },

        $onOpen: function() {
            if (!this.getAttribute('globalProcessId') && !this.getAttribute('hash')) {
                this.close();
                return;
            }

            if (!this.$Comments) {
                return;
            }

            this.Loader.show();

            QUIAjax.get('package_quiqqer_erp_ajax_process_getProcess', (result) => {
                this.setAttributes({
                    icon: 'fa fa-timeline',
                    title: QUILocale.get(lg, 'panel.globalProcess.title', {
                        globalProcessId: result.globalProcessId
                    })
                });

                this.refresh();

                this.$Comments.clear();
                this.$Comments.unserialize(result.history);
                this.Loader.hide();
            }, {
                'package': 'quiqqer/erp',
                globalProcessId: this.getAttribute('globalProcessId'),
                hash: this.getAttribute('hash'),
            });
        }
    });
});
