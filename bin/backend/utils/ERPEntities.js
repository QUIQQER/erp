/**
 * @module package/quiqqer/erp/bin/backend/utils/ERPEntities
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/utils/ERPEntities', function() {
    'use strict';

    return {

        /**
         * Return the AMD Panel for the entity type
         *
         * @param {String} entityType
         * @return {String}
         */
        getPanelByEntity: function(entityType) {
            switch (entityType) {
                case 'QUI\\ERP\\Order\\Order':
                    return 'package/quiqqer/order/bin/backend/controls/panels/Order';

                case 'QUI\\ERP\\Accounting\\Invoice\\Invoice':
                    return 'package/quiqqer/invoice/bin/backend/controls/panels/Invoice';

                case 'QUI\\ERP\\Accounting\\Invoice\\InvoiceTemporary':
                    return 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice';

                case 'QUI\\ERP\\SalesOrders\\SalesOrder':
                    return 'package/quiqqer/salesorders/bin/js/backend/controls/panels/SalesOrder';

                case 'QUI\\ERP\\Accounting\\Offers\\Offer':
                    return 'package/quiqqer/offers/bin/js/backend/controls/panels/Offer';

                case 'QUI\\ERP\\Accounting\\Offers\\OfferTemporary':
                    return 'package/quiqqer/offers/bin/js/backend/controls/panels/TemporaryOffer';

                case 'QUI\\ERP\\Accounting\\Contracts\\Contract':
                    return 'package/quiqqer/contracts/bin/backend/controls/panels/Contract';

                case 'QUI\\ERP\\Purchasing\\Processes\\PurchasingProcessDraft':
                    return 'package/quiqqer/purchasing/bin/js/backend/controls/panels/processes/ProcessDraft';

                case 'QUI\\ERP\\Purchasing\\Processes\\PurchasingProcess':
                    return 'package/quiqqer/purchasing/bin/js/backend/controls/panels/processes/Process';

                default:
                    console.error('missing', entityType);
            }

            return '';
        },

        getEntityTitle: function(uuid) {
            return new Promise(function(resolve) {
                require(['Ajax'], function(QUIAjax) {
                    QUIAjax.get('package_quiqqer_erp_ajax_getEntityTitle', resolve, {
                        'package': 'quiqqer/erp',
                        uuid: uuid
                    });
                });
            });
        },

        openPanelByUUID: function(uuid) {
            return this.getTypeByUUID(uuid).then((entityType) => {
                const panel = this.getPanelByEntity(entityType);

                return new Promise(function(resolve) {
                    require(['utils/Panels', panel], (PanelUtils, Panel) => {
                        const PanelInstance = new Panel({
                            uuid: uuid
                        });

                        PanelUtils.openPanelInTasks(PanelInstance);
                        resolve(PanelInstance);
                    });
                });
            });
        },

        getTypeByUUID: function(uuid) {
            return new Promise(function(resolve) {
                require(['Ajax'], function(QUIAjax) {
                    QUIAjax.get('package_quiqqer_erp_ajax_getEntityType', resolve, {
                        'package': 'quiqqer/erp',
                        uuid: uuid
                    });
                });
            });
        }
    };
});