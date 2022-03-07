/**
 * Class BankAccounts
 *
 * Bank account managing for ecoyn.
 *
 * @module package/quiqqer/erp/bin/backend/controls/settings/BankAccounts
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/erp/bin/backend/controls/settings/BankAccounts', [

    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',

    'qui/utils/Form',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/settings/BankAccounts.html',
    'text!package/quiqqer/erp/bin/backend/controls/settings/BankAccounts.Entry.html',
    'css!package/quiqqer/erp/bin/backend/controls/settings/BankAccounts.css',

], function (QUIControl, QUIConfirm, QUILoader, QUIButton, QUIFormUtils, QUILocale, QUIAjax, Mustache,
             template, templateEntry) {
    "use strict";

    const pkg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/BankAccounts',

        Binds: [
            '$update',
            '$buildList',
            '$onCreateClick',
            '$onEditClick',
            '$onDeleteClick',
            '$openDeleteFinalConfirmation'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input        = null;
            this.$Container    = null;
            this.Loader        = new QUILoader();
            this.$BankAccounts = {};

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         *
         * @return {void}
         */
        $onImport: function () {
            this.$Input = this.getElm();

            // Build template
            if (this.$Input.value !== '') {
                this.$BankAccounts = JSON.decode(this.$Input.value);
            } else {
                this.$BankAccounts = {};
            }

            this.$Container = new Element('div', {
                'class': 'quiqqer-erp-settings-bankaccounts',
            }).inject(this.$Input, 'after');

            this.$buildList();
        },

        /**
         * Build bank account list.
         *
         * @return {void}
         */
        $buildList: function () {
            this.$Container.set('html', Mustache.render(template, {
                bankAccounts     : Object.values(this.$BankAccounts).length ? Object.values(this.$BankAccounts) : false,
                labelDefaultEntry: QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelDefaultEntry'),
                titleEdit        : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.titleEdit'),
                titleDelete      : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.titleDelete'),
            }));

            this.$Container.getElements('.quiqqer-erp-settings-bankaccounts-entry-actions-edit').addEvent(
                'click',
                this.$onEditClick
            );

            this.$Container.getElements('.quiqqer-erp-settings-bankaccounts-entry-actions-delete').addEvent(
                'click',
                this.$onDeleteClick
            );

            new QUIButton({
                textimage: 'fa fa-plus',
                text     : QUILocale.get(pkg, 'controls.BankAccounts.btn.create'),
                title    : QUILocale.get(pkg, 'controls.BankAccounts.btn.create'),
                events   : {
                    onClick: this.$onCreateClick
                }
            }).inject(this.$Container.getElement('.quiqqer-erp-settings-bankaccounts-actions'));
        },

        /**
         * Create new bank account.
         *
         * @return {void}
         */
        $onCreateClick: function () {
            new QUIConfirm({
                maxHeight: 700,
                maxWidth : 600,

                autoclose         : false,
                backgroundClosable: true,

                title: QUILocale.get(pkg, 'controls.BankAccounts.Entry.add.title'),
                icon : 'fa fa-plus',

                cancel_button: {
                    text     : false,
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : QUILocale.get(pkg, 'controls.BankAccounts.Entry.add.btn.submit'),
                    textimage: 'icon-ok fa fa-check'
                },
                events       : {
                    onOpen  : (Win) => {
                        const Content = Win.getContent();

                        Content.set('html', Mustache.render(templateEntry, {
                            labelTitle             : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelTitle'),
                            labelName              : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelName'),
                            labelIban              : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelIban'),
                            labelBic               : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelBic'),
                            labelCreditorId        : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelCreditorId'),
                            labelDefault           : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelDefault'),
                            descDefault            : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.descDefault'),
                            labelAccountHolder     : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelAccountHolder'),
                            labelFinancialAccountNo: QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelFinancialAccountNo'),
                            descFinancialAccountNo : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.descFinancialAccountNo'),
                        }));

                        Content.getElement('input[name="title"]').focus();
                    },
                    onSubmit: (Win) => {
                        const Form = Win.getContent().getElement('form');

                        if (!Form.reportValidity()) {
                            return;
                        }

                        const BankAccount = QUIFormUtils.getFormData(Form);

                        // Generate random new id
                        let bankAccountId;

                        do {
                            bankAccountId = parseInt(performance.now());
                        } while (bankAccountId in this.$BankAccounts);

                        BankAccount.id = bankAccountId;

                        if (BankAccount.default) {
                            for (const OtherBankAccount of Object.values(this.$BankAccounts)) {
                                OtherBankAccount.default = false;
                            }
                        }

                        this.$BankAccounts[BankAccount.id] = BankAccount;
                        this.$update();

                        Win.close();
                    }
                }
            }).open();
        },

        /**
         * Edit existing bank account
         *
         * @param {Event} event
         * @return {void}
         */
        $onEditClick: function (event) {
            let bankAccountId = event.target.get('data-id');

            if (!bankAccountId) {
                bankAccountId = event.target.getParent('.quiqqer-erp-settings-bankaccounts-entry-actions-edit').get(
                    'data-id'
                );
            }

            const BankAccount = this.$BankAccounts[bankAccountId];

            new QUIConfirm({
                maxHeight: 700,
                maxWidth : 600,

                autoclose         : false,
                backgroundClosable: true,

                title: QUILocale.get(pkg, 'controls.BankAccounts.Entry.edit.title'),
                icon : 'fa fa-edit',

                cancel_button: {
                    text     : false,
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : QUILocale.get(pkg, 'controls.BankAccounts.Entry.edit.btn.submit'),
                    textimage: 'icon-ok fa fa-check'
                },
                events       : {
                    onOpen  : (Win) => {
                        const Content = Win.getContent();

                        Content.set('html', Mustache.render(templateEntry, {
                            labelTitle             : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelTitle'),
                            labelName              : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelName'),
                            labelIban              : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelIban'),
                            labelBic               : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelBic'),
                            labelCreditorId        : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelCreditorId'),
                            labelDefault           : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelDefault'),
                            descDefault            : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.descDefault'),
                            labelAccountHolder     : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelAccountHolder'),
                            labelFinancialAccountNo: QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.labelFinancialAccountNo'),
                            descFinancialAccountNo : QUILocale.get(pkg, 'controls.BankAccounts.Entry.tpl.descFinancialAccountNo'),
                        }));

                        const Form = Content.getElement('form');

                        QUIFormUtils.setDataToForm(BankAccount, Form);
                    },
                    onSubmit: (Win) => {
                        const Form = Win.getContent().getElement('form');

                        if (!Form.reportValidity()) {
                            return;
                        }

                        const BankAccountData = QUIFormUtils.getFormData(Form);

                        if (BankAccountData.default) {
                            for (const OtherBankAccount of Object.values(this.$BankAccounts)) {
                                OtherBankAccount.default = false;
                            }
                        }

                        this.$BankAccounts[bankAccountId] = Object.merge(BankAccount, BankAccountData);
                        this.$update();

                        Win.close();
                    }
                }
            }).open();
        },

        /**
         * Delete existing bank account
         *
         * @param {Event} event
         * @return {void}
         */
        $onDeleteClick: function (event) {
            let bankAccountId = event.target.get('data-id');

            if (!bankAccountId) {
                bankAccountId = event.target.getParent('.quiqqer-erp-settings-bankaccounts-entry-actions-delete').get(
                    'data-id'
                );
            }

            const BankAccount = this.$BankAccounts[bankAccountId];

            new QUIConfirm({
                maxHeight: 350,
                maxWidth : 700,

                autoclose         : false,
                backgroundClosable: true,

                title   : QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete.title'),
                icon    : 'fa fa-trash',
                texticon: 'fa fa-trash',

                text       : QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete.text', BankAccount),
                information: QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete.information', BankAccount),

                cancel_button: {
                    text     : false,
                    textimage: 'fa fa-close'
                },
                ok_button    : {
                    text     : QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete.btn.submit'),
                    textimage: 'fa fa-trash'
                },
                events       : {
                    onSubmit: (Win) => {
                        this.$openDeleteFinalConfirmation(bankAccountId);
                        Win.close();
                    }
                }
            }).open();
        },

        /**
         * Final confirmation of deletion.
         *
         * @param {Number} bankAccountId
         * @return {void}
         */
        $openDeleteFinalConfirmation: function (bankAccountId) {
            const BankAccount = this.$BankAccounts[bankAccountId];

            new QUIConfirm({
                maxHeight: 350,
                maxWidth : 700,

                autoclose         : false,
                backgroundClosable: true,

                title   : QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete_final.title'),
                icon    : 'fa fa-exclamation-triangle',
                texticon: 'fa fa-exclamation-triangle',

                text       : QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete_final.text', BankAccount),
                information: QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete_final.information', BankAccount),

                cancel_button: {
                    text     : false,
                    textimage: 'fa fa-close'
                },
                ok_button    : {
                    text     : QUILocale.get(pkg, 'controls.BankAccounts.Entry.delete_final.btn.submit'),
                    textimage: 'fa fa-trash'
                },
                events       : {
                    onOpen  : (Win) => {
                        Win.getButton('submit').getElm().addClass('btn-red');
                    },
                    onSubmit: (Win) => {
                        delete this.$BankAccounts[bankAccountId];
                        this.$update();

                        Win.close();
                    }
                }
            }).open();
        },

        /**
         * Update internal input value for settings.
         *
         * @return {void}
         */
        $update: function () {
            this.$Input.value = JSON.encode(this.$BankAccounts);
            this.$buildList();
        },

        /**
         * GET
         *
         * @return {Promise}
         */
        getMethod: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('ajax_get_method', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * POST
         *
         * @return {Promise}
         */
        postMethod: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('ajax_post_method', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        }
    });
});
