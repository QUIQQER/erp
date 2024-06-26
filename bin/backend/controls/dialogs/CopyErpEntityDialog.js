/**
 * Create a copy of an erp entity
 *
 * @module package/quiqqer/erp/bin/backend/controls/dialogs/CopyErpEntityDialog
 * @author www.pcsg.de (Henning)
 *
 * @event onSuccess [self, newCopy] - Fires if a copy has been successfully created
 * @event onError [self] - Fires if an error occurs during copy creation
 */
define('package/quiqqer/erp/bin/backend/controls/dialogs/CopyErpEntityDialog', [

    'qui/controls/windows/Confirm',
    'Ajax',
    'Locale'

], function(QUIConfirm, QUIAjax, QUILocale) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        type: 'package/quiqqer/erp/bin/backend/controls/dialogs/CopyErpEntityDialog',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            hash: false,

            maxHeight: 400,
            maxWidth: 700,

            icon: 'fa fa-copy',
            texticon: 'fa fa-copy',
            text: QUILocale.get(lg, 'controls.elements.copyDialog.text'),
            autoclose: false,
            ok_button: {
                text: QUILocale.get(lg, 'controls.elements.copyDialog.ok_btn'),
                textimage: 'fa fa-copy'
            }
        },

        initialize: function(options) {
            this.parent(options);

            this.$CopyHistoryCheckbox = null;

            this.addEvents({
                onOpen: this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        getData: function() {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_getEntity', resolve, {
                    'package': 'quiqqer/erp',
                    uuid: this.getAttribute('hash'),
                    onError: reject
                });
            });
        },

        /**
         * event: on open
         */
        $onOpen: function() {
            this.Loader.show();

            this.getData().then((erpEntity) => {
                this.setAttributes({
                    title: QUILocale.get(lg, 'controls.elements.copyDialog.title', {
                        prefixedNumber: erpEntity.prefixedNumber
                    })
                });

                this.getContent().getElement('.text').innerHTML = QUILocale.get(
                    lg,
                    'controls.elements.copyDialog.text',
                    {prefixedNumber: erpEntity.prefixedNumber}
                );

                this.getContent().getElement('.information').innerHTML = QUILocale.get(
                    lg,
                    'controls.elements.copyDialog.information',
                    {prefixedNumber: erpEntity.prefixedNumber}
                );

                this.refresh();

                new Element('label', {
                    styles: {
                        display: 'block',
                        minWidth: 500,
                        width: '80%'
                    },
                    html: '' +
                        '<span class="quiqqer-contracts-dialog-copy-option-title">' +
                        QUILocale.get(lg, 'controls.elements.copyDialog.option.title') +
                        '</span>' +
                        '<span class="quiqqer-contracts-dialog-copy-option-info" style="display: none">' +
                        QUILocale.get(lg, 'controls.elements.copyDialog.option.info') +
                        '</span>' +
                        '<select name="copy-option" required style="width: 100%; margin-top: 10px">' +
                        '   <option value=""></option>' +
                        '   <option value="new">' +
                        '' + QUILocale.get(lg, 'controls.elements.copyDialog.option.new') +
                        '   </option>' +
                        '   <option value="existing">' +
                        '' + QUILocale.get(lg, 'controls.elements.copyDialog.option.existing') +
                        '   </option>' +
                        '</select>'
                }).inject(this.getContent().getElement('.information'));

                this.Loader.hide();
                this.getContent().getElement('[name="copy-option"]').focus();
            });
        },

        /**
         * event: on submit
         */
        $onSubmit: function() {
            const Copy = this.getContent().getElement('[name="copy-option"]');

            if ('reportValidity' in Copy) {
                Copy.reportValidity();

                if ('checkValidity' in Copy) {
                    if (Copy.checkValidity() === false) {
                        return;
                    }
                }
            }

            // no html5 support
            if (Copy.value === '') {
                return;
            }

            this.Loader.show();

            QUIAjax.post('package_quiqqer_erp_ajax_copyEntity', (newCopy) => {
                this.close();
                this.fireEvent('success', [this, newCopy]);

                require([
                    'package/quiqqer/erp/bin/backend/utils/ERPEntities'
                ], function(ErpUtils) {
                    ErpUtils.openPanelByUUID(newCopy.hash);
                });
            }, {
                'package': 'quiqqer/erp',
                uuid: this.getAttribute('hash'),
                processKeepStatus: Copy.value,
                onError: () => {
                    this.fireEvent('error', [self]);
                    this.Loader.hide();
                }
            });
        }
    });
});