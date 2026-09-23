/**
 * Open the customer panel for the user of an XML settings button.
 */
define('package/quiqqer/erp/bin/backend/controls/ErpUserData', [
    'package/quiqqer/customer/bin/backend/controls/customer/Panel',
    'utils/Panels'
], function (CustomerPanel, Panels) {
    'use strict';

    return function (Button) {
        const Panel = Button.getAttribute('Panel');

        Panels.openPanelInTasks(new CustomerPanel({
            userId: Panel.getUser().getId()
        }));
    };
});
