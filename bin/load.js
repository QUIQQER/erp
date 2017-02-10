document.addEvent('domready', function () {
    "use strict";

    require([
        'qui/QUI',
        'package/quiqqer/erp/bin/controls/Panel'
    ], function (QUI, Panel) {
        QUI.addEvent('quiqqerLoaded', function () {
            var ColumnElm = document.getElement('.qui-column'),
                Column = QUI.Controls.getById(ColumnElm.get('data-quiid'));

            var panels = Column.getChildren();

            for (var i in panels) {
                if (!panels.hasOwnProperty(i)) {
                    continue;
                }

                if (panels[i].getType() === 'package/quiqqer/erp/bin/controls/Panel') {
                    return;
                }
            }

            Column.appendChild(new Panel());
        });
    });
});
