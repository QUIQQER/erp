/**
 * Bank accounts managed independently of the surrounding settings form.
 */
define('package/quiqqer/erp/bin/backend/controls/settings/BankAccounts', [
    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',
    'Locale',
    'Ajax',
    'Mustache',
    'text!package/quiqqer/erp/bin/backend/controls/settings/BankAccounts.html',
    'text!package/quiqqer/erp/bin/backend/controls/settings/BankAccounts.Entry.html',
    'css!package/quiqqer/erp/bin/backend/controls/settings/BankAccounts.css'
], function (QUI, QUIControl, QUIConfirm, QUIFormUtils, QUILocale, QUIAjax, Mustache, template, templateEntry) {
    'use strict';

    const pkg = 'quiqqer/erp';
    const locale = (key, params) => QUILocale.get(pkg, 'controls.BankAccounts.' + key, params);

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/settings/BankAccounts',

        initialize: function (options) {
            this.parent(options);
            this.$BankAccounts = {};
            this.$Container = null;
            this.addEvents({onImport: this.$onImport});
        },

        $onImport: function () {
            // The accounts are not form values: a global save must never restore an old snapshot.
            const Input = this.getElm();
            Input.removeAttribute('name');
            this.$Container = document.createElement('div');
            this.$Container.className = 'quiqqer-erp-settings-bankaccounts';
            Input.after(this.$Container);
            const Loading = document.createElement('div');
            Loading.className = 'quiqqer-erp-settings-bankaccounts-loading';
            Loading.setAttribute('role', 'status');

            const Spinner = document.createElement('span');
            Spinner.className = 'fa fa-spinner fa-spin';
            Spinner.setAttribute('aria-hidden', 'true');

            const Label = document.createElement('span');
            Label.textContent = locale('loading');
            Loading.appendChild(Spinner);
            Loading.appendChild(Label);
            this.$Container.appendChild(Loading);

            return this.$request('getList').then((accounts) => {
                this.$BankAccounts = accounts;
                this.$buildList();
            }).catch(() => {
                this.$Container.replaceChildren();
                const Error = document.createElement('p');
                Error.className = 'q-message q-message-error quiqqer-erp-settings-bankaccounts-error';
                Error.setAttribute('role', 'alert');
                Error.textContent = locale('loadError');
                this.$Container.appendChild(Error);
            });
        },

        $buildList: function () {
            const labels = this.$labels();
            this.$Container.innerHTML = Mustache.render(template, Object.assign(labels, {
                bankAccounts: Object.values(this.$BankAccounts),
                immediateSave: locale('immediateSave'),
                empty: locale('empty'),
                labelCreate: locale('btn.create'),
                labelDefaultEntry: locale('Entry.tpl.labelDefaultEntry'),
                titleEdit: locale('Entry.tpl.titleEdit'),
                titleDelete: locale('Entry.tpl.titleDelete')
            }));

            this.$Container.querySelector('[data-name="create"]').addEventListener('click', () => this.$openEditor());
            this.$Container.querySelectorAll('[data-name="edit"]').forEach((Button) => {
                Button.addEventListener('click', () => this.$openEditor(Button.dataset.id));
            });
            this.$Container.querySelectorAll('[data-name="delete"]').forEach((Button) => {
                Button.addEventListener('click', () => this.$openDeleteConfirmation(Button.dataset.id));
            });
        },

        $labels: function () {
            const labels = {};
            [
                'labelTitle', 'labelName', 'labelIban', 'labelBic', 'labelCreditorId', 'labelDefault',
                'descDefault', 'labelAccountHolder', 'labelFinancialAccountNo', 'descFinancialAccountNo'
            ].forEach((key) => {
                labels[key] = locale('Entry.tpl.' + key);
            });
            return labels;
        },

        $openEditor: function (id) {
            const action = typeof id === 'undefined' ? 'add' : 'edit';
            new QUIConfirm({
                maxHeight: 700,
                maxWidth: 600,
                autoclose: false,
                backgroundClosable: true,
                title: locale('Entry.' + action + '.title'),
                icon: action === 'add' ? 'fa fa-plus' : 'fa fa-edit',
                cancel_button: {text: false, textimage: 'fa fa-remove'},
                ok_button: {text: locale('Entry.' + action + '.btn.submit'), textimage: 'fa fa-check'},
                events: {
                    onOpen: (Win) => {
                        const Content = Win.getContent();
                        Content.innerHTML = Mustache.render(templateEntry, this.$labels());
                        const Form = Content.querySelector('[data-name="bank-account-form"]');

                        if (action === 'edit') {
                            QUIFormUtils.setDataToForm(this.$BankAccounts[id], Form);
                        }

                        Content.querySelector('[data-name="title"]').focus();
                    },
                    onSubmit: (Win) => {
                        const Form = Win.getContent().querySelector('[data-name="bank-account-form"]');
                        if (!Form.reportValidity()) {
                            return;
                        }

                        return this.$persist(Win, 'save', {
                            id: typeof id === 'undefined' ? '' : id,
                            data: JSON.stringify(QUIFormUtils.getFormData(Form))
                        });
                    }
                }
            }).open();
        },

        $openDeleteConfirmation: function (id, finalConfirmation) {
            const key = finalConfirmation ? 'delete_final' : 'delete';
            // Confirm renders HTML. Escape account data before interpolating translated text.
            const account = {};
            Object.entries(this.$BankAccounts[id]).forEach(([name, value]) => {
                account[name] = Mustache.escape(String(value));
            });

            new QUIConfirm({
                maxHeight: 350,
                maxWidth: 700,
                autoclose: false,
                backgroundClosable: true,
                title: locale('Entry.' + key + '.title'),
                icon: 'fa fa-trash',
                texticon: finalConfirmation ? 'fa fa-exclamation-triangle' : 'fa fa-trash',
                text: locale('Entry.' + key + '.text', account),
                information: locale('Entry.' + key + '.information', account),
                cancel_button: {text: false, textimage: 'fa fa-close'},
                ok_button: {text: locale('Entry.' + key + '.btn.submit'), textimage: 'fa fa-trash'},
                events: {
                    onOpen: (Win) => {
                        if (finalConfirmation) {
                            Win.getButton('submit').getElm().classList.add('btn-red');
                        }
                    },
                    onSubmit: (Win) => {
                        if (finalConfirmation) {
                            return this.$persist(Win, 'delete', {id: id});
                        }

                        Win.close();
                        this.$openDeleteConfirmation(id, true);
                    }
                }
            }).open();
        },

        $persist: function (Win, action, params) {
            if (Win.$bankAccountSaving) {
                return;
            }

            Win.$bankAccountSaving = true;
            const Submit = Win.getButton('submit');
            Submit.disable();
            let saved = false;
            const Content = Win.getContent();
            const previousError = Content.querySelector('[data-name="save-error"]');
            if (previousError) {
                previousError.remove();
            }

            return this.$request(action, params).then((accounts) => {
                this.$BankAccounts = accounts;
                this.$buildList();
                saved = true;
                Win.close();
                this.$Container.querySelector('[data-name="create"]').focus();
                QUI.getMessageHandler().then((MessageHandler) => {
                    MessageHandler.addSuccess(locale('saved'));
                });
            }).catch(() => {
                const Error = document.createElement('p');
                Error.dataset.name = 'save-error';
                Error.className = 'q-message q-message-error quiqqer-erp-settings-bankaccounts-error';
                Error.setAttribute('role', 'alert');
                Error.textContent = locale('saveError');
                Content.appendChild(Error);
            }).finally(() => {
                Win.$bankAccountSaving = false;
                if (!saved) {
                    Submit.enable();
                }
            });
        },

        $request: function (action, params) {
            return new Promise((resolve, reject) => {
                QUIAjax[action === 'getList' ? 'get' : 'post'](
                    'package_quiqqer_erp_ajax_settings_bankAccounts_' + action,
                    resolve,
                    Object.assign({package: pkg, onError: reject}, params)
                );
            });
        }
    });
});
