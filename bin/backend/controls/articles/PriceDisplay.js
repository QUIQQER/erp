define('package/quiqqer/erp/bin/backend/controls/articles/PriceDisplay', [
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'Locale',
    URL_OPT_DIR + 'quiqqer/core/bin/QUI/lib/tippy/tippy.min.js',
    'css!' + URL_OPT_DIR + 'quiqqer/core/bin/QUI/lib/tippy/tippy.css',
    'css!package/quiqqer/erp/bin/backend/controls/articles/PriceDisplay.css'
], function (QUIControl, QUIButton, QUILocale, tippy) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/PriceDisplay',

        options: {
            netto: true,
            warning: ''
        },

        create: function () {
            this.$Elm = document.createElement('div');
            this.$Elm.className = 'quiqqer-erp-priceDisplay';

            const Buttons = document.createElement('div');
            Buttons.className = 'quiqqer-erp-priceDisplay-buttons';
            Buttons.setAttribute('role', 'group');
            Buttons.setAttribute('aria-label', QUILocale.get(lg, 'article.summary.tpl.labelNet') + ' / ' +
                QUILocale.get(lg, 'article.summary.tpl.labelGross'));

            this.$Description = document.createElement('span');
            this.$Description.className = 'quiqqer-erp-priceDisplay-description';
            this.$Description.dataset.name = 'description';
            this.$Description.id = 'price-display-' + this.getId();
            this.$Caption = document.createElement('span');
            this.$Caption.className = 'quiqqer-erp-priceDisplay-caption';
            this.$Caption.setAttribute('aria-live', 'polite');
            this.$Description.appendChild(this.$Caption);
            Buttons.setAttribute('aria-describedby', this.$Description.id);
            this.$Warning = document.createElement('span');
            this.$Warning.className = 'quiqqer-erp-priceDisplay-warning';
            this.$Warning.setAttribute('role', 'img');
            this.$Warning.dataset.name = 'warning';
            this.$Warning.hidden = true;
            const Icon = document.createElement('span');
            Icon.className = 'fa fa-exclamation-triangle';
            Icon.setAttribute('aria-hidden', 'true');
            this.$Warning.appendChild(Icon);
            this.$Elm.append(Buttons, this.$Description, this.$Warning);

            const tooltipOptions = {
                allowHTML: false,
                trigger: 'mouseenter focusin',
                onShow: (Tooltip) => {
                    let zIndex = 0;

                    for (let Parent = this.$Elm; Parent; Parent = Parent.parentElement) {
                        zIndex = Math.max(zIndex, Number.parseInt(getComputedStyle(Parent).zIndex, 10) || 0);
                    }

                    Tooltip.setProps({zIndex: zIndex + 1});
                }
            };
            this.$Tooltip = tippy(this.$Elm, Object.assign({content: ''}, tooltipOptions));

            this.$buttons = [true, false].map((netto) => {
                const Button = new QUIButton({
                    text: QUILocale.get(lg, netto ? 'article.summary.tpl.labelNet' : 'article.summary.tpl.labelGross'),
                    events: {
                        onClick: () => {
                            if (this.getValue() === netto) {
                                return;
                            }

                            this.setValue(netto);
                            this.fireEvent('change', [this, netto]);
                        }
                    }
                }).inject(Buttons);

                Button.getElm().tabIndex = 0;
                Button.getElm().style.removeProperty('outline');
                return Button;
            });

            this.addEvent('onDestroy', () => {
                this.$buttons.forEach((Button) => Button.destroy());
                this.$Tooltip.destroy();
            });
            this.setValue(this.getAttribute('netto'));
            this.setWarning(this.getAttribute('warning'));
            return this.$Elm;
        },

        getValue: function () {
            return !!this.getAttribute('netto');
        },

        setWarning: function (message) {
            this.setAttribute('warning', message);

            if (!this.$Warning) {
                return;
            }

            this.$Elm.classList.toggle('quiqqer-erp-priceDisplay--warning', !!message);
            this.$Warning.hidden = !message;
            this.$Warning.setAttribute('aria-label', message);
            this.$refreshTooltip();
        },

        setValue: function (netto) {
            this.setAttribute('netto', !!netto);

            if (!this.$buttons) {
                return;
            }

            this.$Caption.textContent = QUILocale.get(lg, this.getValue() ?
                'article.priceDisplay.net' : 'article.priceDisplay.gross');
            this.$refreshTooltip();

            this.$buttons.forEach((Button, index) => {
                const selected = (index === 0) === this.getValue();

                if (selected) {
                    Button.setActive();
                } else {
                    Button.setNormal();
                }

                Button.getElm().setAttribute('aria-pressed', String(selected));
            });
        },

        $refreshTooltip: function () {
            const FullText = document.createElement('div');
            FullText.innerHTML = QUILocale.get(lg, this.getValue() ?
                'control.articleList.netto.message' : 'control.articleList.brutto.message');
            const message = this.getAttribute('warning') || FullText.textContent.replace(/\s+/g, ' ').trim();
            this.$Tooltip.setContent(message);
        }
    });
});
