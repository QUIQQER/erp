/**
 * Select for bank accounts.
 *
 * @module package/quiqqer/erp/bin/backend/controls/bankAccounts/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onBankAccountChange [self, value]
 */
define('package/quiqqer/erp/bin/backend/controls/bankAccounts/Select', [

    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',

    'Ajax',

    'css!package/quiqqer/erp/bin/backend/controls/bankAccounts/Select.css'

], function (QUISelect, QUILoader, QUIAjax) {
    "use strict";

    const pkg = 'quiqqer/erp';

    return new Class({

        Extends: QUISelect,
        Type   : 'package/quiqqer/erp/bin/backend/controls/bankAccounts/Select',

        Binds: [
            '$onInject',
            '$onCreate',
            '$onImport',
            '$load',
            '$onChange'
        ],

        options: {
            showIcons   : false,
            searchable  : true,
            initialValue: false    // sets an initial value for the dropdown menu (if it exists!)
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onImport: this.$onImport,
                onChange: this.$onChange
            });

            this.Loader     = new QUILoader();
            this.$currentId = null;
        },

        /**
         * event on DOMElement creation
         */
        $onCreate: function () {
            this.$Elm.addClass('quiqqer-countries-select');
            this.$Elm.set('data-qui', 'package/quiqqer/erp/bin/backend/controls/bankAccounts/Select');
            this.$Elm.set('data-quiid', this.getId());

            this.Loader.inject(this.$Content);
        },

        /**
         * event: on control import
         */
        $onImport: function () {
            this.$Input = this.getElm();
            const Elm   = this.create();

            Elm.addClass('quiqqer-countries-select');
            Elm.set('data-qui', 'package/quiqqer/erp/bin/backend/controls/bankAccounts/Select');
            Elm.set('data-quiid', this.getId());

            if (this.$Input.nodeName === 'INPUT') {
                this.$Input.type = 'hidden';

                if (this.$Input.value !== '') {
                    this.$currentId = this.$Input.value;
                }

                Elm.inject(this.$Input, 'after');

                this.$load();
                return;
            }

            if (this.$Input.nodeName === 'SELECT') {
                const optionElms = this.$Input.getElements('option');

                Elm.inject(this.$Input, 'after');

                for (let i = 0, len = optionElms.length; i < len; i++) {
                    let OptionElm = optionElms[i];

                    this.appendChild(
                        OptionElm.innerText,
                        OptionElm.value,
                        false
                    );
                }

                this.$Input.setStyle('display', 'none');
                this.setValue(this.$Input.value);
            }
        },

        /**
         * event: on control inject
         */
        $onInject: function () {
            this.$load();
        },

        /**
         * event: onChange Select
         *
         * @param {string} value - selected value
         * @return {void}
         */
        $onChange: function (value) {
            if (!this.$Input) {
                return;
            }

            this.$Input.value = value;
            this.fireEvent('bankAccountChange', [this, value]);
        },

        /**
         * Load data
         *
         * @return {void}
         */
        $load: function () {
            this.Loader.show();

            this.$getBankAccounts().then((bankAccounts) => {
                for (const BankAccount of Object.values(bankAccounts)) {
                    this.appendChild(
                        "<b>" + BankAccount.title + "</b> (" + BankAccount.iban + " - " + BankAccount.name + ")",
                        BankAccount.id,
                        'fa fa-university'
                    );
                }

                if (this.$currentId in bankAccounts) {
                    this.setValue(this.$currentId);
                }

                this.Loader.hide();

                this.$Elm.set('data-quiid', this.getId());
                this.fireEvent('load', [this]);
            });
        },

        /**
         * Get bank account list.
         *
         * @return {Promise<Object>}
         */
        $getBankAccounts: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_settings_bankAccounts_getList', resolve, {
                    'package': pkg,
                    onError  : reject,
                });
            });
        }
    });
});
