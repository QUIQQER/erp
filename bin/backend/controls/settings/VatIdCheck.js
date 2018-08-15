/**
 * @module package/quiqqer/erp/bin/backend/controls/settings/VatIdCheck
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/settings/VatIdCheck', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale'

], function (QUI, QUIControl, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/VatIdCheck',

        Bind: [
            '$onImport',
            'check'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input  = null;
            this.$Status = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Check the vat id
         *
         * @return {Promise}
         */
        check: function () {
            var self = this;

            this.$Status.set('html', '<span class="fa fa-spinner fa-spin"></span>');

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_settings_validateVat', function (result) {
                    resolve(result);

                    if (result === -1) {
                        self.$Status.set('html', '<span class="fa fa-minus"></span>');
                        return;
                    }

                    if (result === 1) {
                        self.$Status.set('html', '<span class="fa fa-check"></span>');
                        return;
                    }

                    self.$Status.set('html', '<span class="fa fa-bolt"></span>');
                }, {
                    'package': 'quiqqer/erp',
                    vatId    : self.$Input.value,
                    onError  : reject
                });
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();
            this.$Elm   = new Element('div').wraps(this.$Input);

            if (this.$Input.hasClass('field-container-field')) {
                this.$Elm.addClass('field-container-field');
                this.$Elm.addClass('field-container-field-no-padding');

                this.$Input.removeClass('field-container-field');
                this.$Input.setStyles({
                    border: 'none',
                    width : '100%'
                });
            }

            this.$timeout = null;

            this.$Input.addEvent('keyup', function () {
                if (self.$timeout) {
                    clearTimeout(self.$timeout);
                }

                self.$timeout = (function () {
                    self.check();
                }).delay(300);
            });

            this.$Status = new Element('div', {
                'class': 'field-container-item',
                html   : '<span class="fa fa-minus"></span>',
                styles : {
                    textAlign: 'center',
                    width    : 50
                }
            }).inject(this.$Elm, 'after');

            console.log('$onImport');
        }
    });
});
