/**
 * @module package/quiqqer/erp/bin/backend/controls/process/ProcessGrid
 */
define('package/quiqqer/erp/bin/backend/controls/process/ProcessGrid', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'controls/grid/Grid',
    'Locale',
    'Ajax'

], function(QUI, QUIControl, QUIButton, QUILoader, Grid, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/process/ProcessGrid',

        Binds: [
            '$onInject',
            'refresh'
        ],

        options: {
            globalProcessId: false,
            entityHash: false
        },

        initialize: function(options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        create: function() {
            this.$Elm = this.parent();
            this.Loader.inject(this.$Elm);

            const Container = new Element('div').inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                height: 300,
                columnModel: [
                    {
                        header: QUILocale.get(lg, 'erp.process.type'),
                        dataIndex: 'type',
                        dataType: 'QUI',
                        width: 60
                    }, {
                        header: QUILocale.get(lg, 'erp.process.state'),
                        dataIndex: 'status',
                        dataType: 'node',
                        width: 100
                    }, {
                        header: QUILocale.get(lg, 'erp.process.prefixedNumber'),
                        dataIndex: 'prefixedNumber',
                        dataType: 'string',
                        width: 180
                    }, {
                        header: QUILocale.get(lg, 'erp.process.uuid'),
                        dataIndex: 'uuid',
                        dataType: 'string',
                        width: 240
                    }
                ],
                pagination: false
            });

            return this.$Elm;
        },

        $onInject: function() {
            this.refresh();
        },

        refresh: function() {
            this.Loader.show();

            QUIAjax.get('package_quiqqer_erp_ajax_process_getEntities', (result) => {
                const data = [];
                console.log(result);

                result.forEach((entry) => {
                    const Type = new QUIButton({
                        events: {
                            click: this.$click
                        },
                        entityType: entry.entityType,
                        uuid: entry.uuid,
                        styles: {
                            width: 40
                        }
                    });

                    switch (entry.entityType) {
                        case 'QUI\\ERP\\Order\\Order':
                            Type.setAttribute('icon', 'fa fa-shopping-basket');
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.order.open'));
                            break;

                        case 'QUI\\ERP\\Accounting\\Invoice\\Invoice':
                            Type.setAttribute('icon', 'fa fa-file-text-o');
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.invoice.open'));
                            break;

                        case 'QUI\\ERP\\Accounting\\Invoice\\InvoiceTemporary':
                            Type.setAttribute('icon', 'fa fa-file-text-o');
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.invoiceTemporary.open'));
                            break;

                        case 'QUI\\ERP\\SalesOrders\\SalesOrder':
                            Type.setAttribute('icon', 'fa fa-suitcase');
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.salesOrder.open'));
                            break;
                    }

                    data.push({
                        type: Type,
                        status: entry.status,
                        prefixedNumber: entry.prefixedNumber,
                        uuid: entry.uuid
                    });
                });

                this.$Grid.setData({
                    data: data
                });

                this.Loader.hide();
            }, {
                'package': 'quiqqer/erp',
                globalProcessId: this.getAttribute('globalProcessId'),
                entityHash: this.getAttribute('entityHash')
            });
        },

        $click: function(Btn) {
            let panel;
            const uuid = Btn.getAttribute('uuid');

            switch (Btn.getAttribute('entityType')) {
                case 'QUI\\ERP\\Order\\Order':
                    panel = 'package/quiqqer/order/bin/backend/controls/panels/Order';
                    break;

                case 'QUI\\ERP\\Accounting\\Invoice\\Invoice':
                    panel = 'package/quiqqer/invoice/bin/backend/controls/panels/Invoice';
                    break;

                case 'QUI\\ERP\\Accounting\\Invoice\\InvoiceTemporary':
                    panel = 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice';
                    break;

                case 'QUI\\ERP\\SalesOrders\\SalesOrder':
                    panel = 'package/quiqqer/salesorders/bin/js/backend/controls/panels/SalesOrder';
                    break;

                default:
                    console.error('missing', uuid, Btn.getAttribute('entityType'));
                    return;
            }

            require(['utils/Panels', panel], (PanelUtils, Panel) => {
                PanelUtils.openPanelInTasks(
                    new Panel({
                        'uuid': uuid
                    })
                );
            });
        }
    });
});
