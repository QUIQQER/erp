document.addEvent('domready', function () {
    "use strict";

    require([
        'qui/QUI',
        'package/quiqqer/erp/bin/backend/controls/Panel'
    ], function (QUI, Panel) {
        var loadExecute = 0;

        var load = function () {
            loadExecute++;

            if (loadExecute === 10) {
                return;
            }
            var ColumnElm = document.getElement('.qui-column');

            if (!ColumnElm) {
                load.delay(100);
                return;
            }

            // require([
            //     'package/quiqqer/customer/bin/backend/controls/CreateCustomerWindow'
            // ], function (Win) {
            //     new Win().open();
            // });

            var Column = QUI.Controls.getById(ColumnElm.get('data-quiid'));

            var panels = Column.getChildren(),
                length = Object.getLength(panels);

            if (length === 0) {
                load.delay(100);
                return;
            }

            for (var i in panels) {
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
    });
});
