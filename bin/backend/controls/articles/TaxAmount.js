define('package/quiqqer/erp/bin/backend/controls/articles/TaxAmount', [
    'qui/controls/Control',
    'Locale',
    'css!package/quiqqer/erp/bin/backend/controls/articles/TaxAmount.css'
], function (QUIControl, QUILocale) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/TaxAmount',

        options: {
            rate: 0,
            amount: 0,
            validateRate: null
        },

        create: function () {
            this.$Elm = document.createElement('div');
            this.$Elm.className = 'quiqqer-erp-taxAmount';
            this.$Elm.setAttribute('role', 'group');
            this.$Elm.setAttribute('aria-label', QUILocale.get(lg, 'manual.taxAmount'));

            const Fields = document.createElement('div');
            Fields.className = 'field-container quiqqer-erp-taxAmount-fields';

            this.$Rate = document.createElement('input');
            this.$Rate.type = 'number';
            this.$Rate.step = 'any';
            this.$Rate.min = 0;
            this.$Rate.max = 100;
            this.$Rate.required = true;
            this.$Rate.className = 'field-container-field';
            this.$Rate.dataset.name = 'rate';
            this.$Rate.setAttribute('aria-label', QUILocale.get(lg, 'manual.taxRate'));

            const Percent = document.createElement('span');
            Percent.className = 'field-container-item';
            Percent.textContent = '%';
            Percent.setAttribute('aria-hidden', 'true');

            this.$Amount = document.createElement('input');
            this.$Amount.type = 'number';
            this.$Amount.step = 'any';
            this.$Amount.required = true;
            this.$Amount.className = 'field-container-field';
            this.$Amount.dataset.name = 'amount';

            this.$Remove = document.createElement('button');
            this.$Remove.type = 'button';
            this.$Remove.className = 'qui-button field-container-field';
            this.$Remove.textContent = '×';
            this.$Remove.dataset.name = 'remove';
            this.$Remove.addEventListener('click', () => this.fireEvent('remove', [this]));

            Fields.append(this.$Rate, Percent, this.$Amount, this.$Remove);
            this.$Elm.appendChild(Fields);
            this.setRate(this.getAttribute('rate'));
            this.setAmount(this.getAttribute('amount'));

            this.$Rate.addEventListener('change', () => {
                this.$Rate.setCustomValidity('');
                const rate = this.$Rate.valueAsNumber;
                const validateRate = this.getAttribute('validateRate');

                if (this.$Rate.checkValidity() && typeof validateRate === 'function') {
                    this.$Rate.setCustomValidity(validateRate(rate) || '');
                }

                if (!this.$Rate.checkValidity() || !Number.isFinite(rate)) {
                    this.$Rate.reportValidity();
                    return;
                }

                this.setRate(rate);
                this.fireEvent('rateChange', [this, rate]);
            });

            this.$Amount.addEventListener('change', () => {
                const amount = this.$Amount.valueAsNumber;

                if (!this.$Amount.checkValidity() || !Number.isFinite(amount)) {
                    this.$Amount.reportValidity();
                    return;
                }

                this.setAmount(amount);
                this.fireEvent('amountChange', [this, amount]);
            });

            return this.$Elm;
        },

        getValue: function () {
            return {
                vat: this.getAttribute('rate'),
                sum: this.getAttribute('amount')
            };
        },

        setRate: function (rate) {
            this.setAttribute('rate', rate);

            if (!this.$Rate) {
                return;
            }

            if (this.$Rate !== document.activeElement) {
                this.$Rate.value = rate;
            }

            this.$Amount.setAttribute('aria-label', QUILocale.get(lg, 'manual.taxAmount') + ' (' + rate + '%)');
            this.$Remove.title = QUILocale.get(lg, 'manual.removeTax') + ' (' + rate + '%)';
            this.$Remove.setAttribute('aria-label', this.$Remove.title);
        },

        setAmount: function (amount) {
            this.setAttribute('amount', amount);

            if (this.$Amount && this.$Amount !== document.activeElement) {
                this.$Amount.value = amount;
            }
        },

        focusRate: function () {
            this.$Rate.focus();
        }
    });
});
