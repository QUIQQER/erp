document.addEvent('domready', function () {
    "use strict";

    require([
        'qui/QUI',
        'package/quiqqer/erp/bin/backend/controls/Panel',
        'package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors',
        'qui/controls/buttons/Separator',
        'Locale'
    ], function (QUI, Panel, PriceFactorsWindow, Separator, QUILocale) {
        let loadExecute = 0;

        const load = function () {
            loadExecute++;

            if (loadExecute === 10) {
                return;
            }

            const ColumnElm = document.getElement('.qui-column');

            if (!ColumnElm) {
                load.delay(100);
                return;
            }

            const Column = QUI.Controls.getById(ColumnElm.get('data-quiid'));

            let panels = Column.getChildren(),
                length = Object.getLength(panels);

            if (length === 0) {
                load.delay(100);
                return;
            }

            for (let i in panels) {
                if (!panels.hasOwnProperty(i)) {
                    continue;
                }

                if (panels[i].getType() === 'package/quiqqer/erp/bin/backend/controls/Panel') {
                    return;
                }
            }

            Column.appendChild(new Panel(), 1);
        };

        QUI.addEvent('quiqqerLoaded', load);


        // extend panels
        QUI.addEvent('quiqqerOrderActionButtonCreate', function (Panel, Actions) {
            Actions.appendChild(new Separator());

            Actions.appendChild({
                name    : 'priceFactors',
                text    : QUILocale.get('quiqqer/erp', 'panel.btn.priceFactors'),
                icon    : 'fa fa-outdent',
                disabled: true,
                events  : {
                    onClick: function () {
                        const quiId = Panel.getElm().getElement(
                            '[data-qui="package/quiqqer/erp/bin/backend/controls/articles/ArticleList"]'
                        ).get('data-quiid');

                        new PriceFactorsWindow({
                            ArticleList: QUI.Controls.getById(quiId)
                        }).open();
                    }
                }
            });

            Actions.appendChild(new Separator());

            QUI.fireEvent('quiqqerOrderActionButtonPriceFactors', [
                Panel,
                Actions
            ]);

            // factors
            const PriceFactors = Actions.getChildren().filter(function (Item) {
                return Item.getAttribute('name') === 'priceFactors';
            })[0];

            Panel.addEvent('categoryEnter', function (Panel, Btn) {
                if (Btn.getAttribute('name') === 'articles') {
                    PriceFactors.enable();
                } else {
                    PriceFactors.disable();
                }
            });
        });
    });
});
