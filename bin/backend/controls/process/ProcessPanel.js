/**
 * @module package/quiqqer/erp/bin/backend/controls/process/ProcessPanel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/process/ProcessPanel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'Locale',
    'Ajax',

    'css!package/quiqqer/erp/bin/backend/controls/process/ProcessPanel.css'

], function(QUI, QUIPanel, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/erp/bin/backend/controls/process/ProcessPanel',

        Binds: [
            '$onCreate',
            '$onShow'
        ],

        options: {
            globalProcessId: false
        },

        initialize: function(options) {
            this.parent(options);

            if (typeof options.globalProcessId !== 'undefined') {
                this.setAttribute('#id', 'process--' + options.globalProcessId);
            }

            this.$Comments = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onShow: this.$onShow
            });
        },

        $onCreate: function() {
            if (!this.getAttribute('globalProcessId')) {
                this.close();
                return;
            }

            this.setAttributes({
                icon: 'fa fa-sitemap',
                title: QUILocale.get(lg, 'panel.globalProcess.title', {
                    globalProcessId: this.getAttribute('globalProcessId')
                })
            });

            this.Loader.show();
            this.refresh();

            this.getBody().setStyles({
                padding: 0
            });

            new Element('div', {
                'class': 'quiqqer-erp-process-comments-header',
                html: '<div class="quiqqer-customer-comments-header-filter">' +
                    '    <input type="text" name="filter" placeholder="Filter (Nachricht, Type, ID) ...">' +
                    '  </div>'
            }).inject(this.getBody());

            require(['package/quiqqer/erp/bin/backend/controls/Comments'], (Comments) => {
                const CommentContainer = new Element('div', {
                    styles: {
                        padding: 20
                    }
                }).inject(this.getBody());

                const Filter = this.getBody().getElement('[name="filter"]');
                this.$Comments = new Comments().inject(CommentContainer);

                Filter.addEvent('keyup', () => {
                    this.$Comments.filter(Filter.value);
                });

                this.$onShow();
            });
        },

        $onShow: function() {
            if (!this.getAttribute('globalProcessId')) {
                this.close();
                return;
            }

            if (!this.$Comments) {
                return;
            }

            this.Loader.show();

            QUIAjax.get('package_quiqqer_erp_ajax_dashboard_globalProcess_getProcess', (result) => {
                this.$Comments.clear();
                this.$Comments.unserialize(result.history);
                this.Loader.hide();
            }, {
                'package': 'quiqqer/erp',
                globalProcessId: this.getAttribute('globalProcessId')
            });
        }
    });
});
