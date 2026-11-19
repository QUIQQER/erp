define('package/quiqqer/erp/bin/backend/controls/articles/CalculationMode', [
    'qui/controls/buttons/Button',
    'qui/controls/contextmenu/Item',
    'Locale',
    'Mustache',
    URL_OPT_DIR + 'quiqqer/core/bin/QUI/lib/tippy/tippy.min.js',
    'text!package/quiqqer/erp/bin/backend/controls/articles/CalculationMode.html',
    'css!' + URL_OPT_DIR + 'quiqqer/core/bin/QUI/lib/tippy/tippy.css',
    'css!package/quiqqer/erp/bin/backend/controls/articles/CalculationMode.css'
], function (QUIButton, MenuItem, QUILocale, Mustache, tippy, template) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({
        Extends: QUIButton,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/CalculationMode',

        Binds: [
            '$refreshMode'
        ],

        options: {
            ArticleList: null,
            textimage: 'fa fa-calculator'
        },

        initialize: function (options) {
            this.parent(options);
            this.$hintTimer = null;

            const ArticleList = this.getAttribute('ArticleList');

            this.$modeItems = ['automatic', 'manual'].map((mode) => {
                const Item = new MenuItem({
                    name: mode,
                    text: QUILocale.get(lg, 'manual.calculation') + ': ' + QUILocale.get(lg, 'manual.' + mode),
                    events: {
                        onClick: () => {
                            if (ArticleList.getCalculationMode() !== mode) {
                                ArticleList.changeCalculationMode(mode).catch(console.error);

                                if (mode === 'manual') {
                                    this.$showManualHint();
                                }
                            }
                        }
                    }
                });

                this.appendChild(Item);
                return Item;
            });

            ArticleList.addEvent('onCalc', this.$refreshMode);
            this.addEvent('onDestroy', () => {
                ArticleList.removeEvent('onCalc', this.$refreshMode);
                clearTimeout(this.$hintTimer);
                this.$Tooltip?.destroy();
            });
            this.$refreshMode();
        },

        create: function () {
            const Elm = this.parent();
            Elm.classList.add('quiqqer-erp-calculationMode');
            Elm.tabIndex = 0;
            Elm.style.removeProperty('outline');
            Elm.setAttribute('aria-haspopup', 'menu');
            Elm.setAttribute('aria-expanded', 'false');
            Elm.removeAttribute('title');

            this.$Tooltip = tippy(Elm, {
                content: QUILocale.get(lg, 'manual.calculation'),
                trigger: 'mouseenter focus',
                placement: 'top',
                animation: 'shift-away',
                popperOptions: {
                    modifiers: [{
                        name: 'flip',
                        enabled: false
                    }]
                },
                allowHTML: false,
                onShow: (Tooltip) => {
                    let zIndex = Number.parseInt(this.$Menu?.getElm().style.zIndex, 10) || 0;

                    for (let Parent = Elm; Parent; Parent = Parent.parentElement) {
                        zIndex = Math.max(zIndex, Number.parseInt(getComputedStyle(Parent).zIndex, 10) || 0);
                    }

                    Tooltip.setProps({zIndex: zIndex + 1});
                }
            });

            Array.from(Elm.children).forEach((Child) => {
                if (!Child.textContent) {
                    Child.setAttribute('aria-hidden', 'true');
                }
            });

            this.getContextMenu((Menu) => {
                Menu.getElm().setAttribute('role', 'menu');
                Menu.addEvent('onShow', () => {
                    let zIndex = 0;

                    for (let Parent = Elm; Parent; Parent = Parent.parentElement) {
                        zIndex = Math.max(zIndex, Number.parseInt(getComputedStyle(Parent).zIndex, 10) || 0);
                    }

                    Menu.getElm().style.zIndex = String(zIndex + 1);
                    Elm.setAttribute('aria-expanded', 'true');
                });
                Menu.addEvent('onHide', () => Elm.setAttribute('aria-expanded', 'false'));

                this.$modeItems.forEach((Item) => {
                    Item.getElm().setAttribute('role', 'menuitemradio');
                    Item.$Icon.setAttribute('aria-hidden', 'true');
                });
                this.$refreshMode();

                Elm.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();

                        if (Elm.getAttribute('aria-expanded') !== 'true') {
                            this.click();
                        }

                        if (event.key === 'ArrowDown') {
                            Menu.down();
                        } else {
                            Menu.up();
                        }
                    } else if (event.key === 'Escape') {
                        Menu.hide();
                        this.$restoreDefaultTooltip();
                    } else if ((event.key === 'Enter' || event.key === ' ') &&
                        Elm.getAttribute('aria-expanded') === 'true') {
                        event.preventDefault();
                        Menu.select();
                        Menu.hide();
                    }
                });
            });

            return Elm;
        },

        $refreshMode: function () {
            const mode = this.getAttribute('ArticleList').getCalculationMode();
            this.setAttribute('text', Mustache.render(template, {
                automaticLabel: QUILocale.get(lg, 'manual.automatic'),
                selectedLabel: QUILocale.get(lg, 'manual.' + mode)
            }));

            if (this.$Elm) {
                this.$Elm.removeAttribute('title');
            }

            if (mode === 'automatic' && this.$hintTimer !== null) {
                this.$restoreDefaultTooltip();
            }

            this.$modeItems.forEach((Item) => {
                const selected = Item.getAttribute('name') === mode;
                Item.setAttribute('icon', selected ? 'fa fa-check' : 'fa fa-fw');

                if (Item.$Elm) {
                    Item.getElm().setAttribute('aria-checked', String(selected));
                }
            });
        },

        $showManualHint: function () {
            clearTimeout(this.$hintTimer);
            this.$Tooltip.setProps({
                trigger: 'manual',
                hideOnClick: false
            });
            this.$Tooltip.setContent(QUILocale.get(lg, 'manual.hint'));
            this.$Tooltip.show();
            this.$hintTimer = setTimeout(() => this.$restoreDefaultTooltip(), 4000);
        },

        $restoreDefaultTooltip: function () {
            clearTimeout(this.$hintTimer);
            this.$hintTimer = null;
            this.$Tooltip.hide();
            this.$Tooltip.setContent(QUILocale.get(lg, 'manual.calculation'));
            this.$Tooltip.setProps({
                trigger: 'mouseenter focus',
                hideOnClick: true
            });
        }
    });
});
