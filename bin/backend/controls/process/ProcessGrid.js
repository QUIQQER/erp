/**
 * @module package/quiqqer/erp/bin/backend/controls/process/ProcessGrid
 */
define('package/quiqqer/erp/bin/backend/controls/process/ProcessGrid', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'package/quiqqer/erp/bin/backend/utils/ERPEntities',
    'controls/grid/Grid',
    'Locale',
    'Ajax'

], function(QUI, QUIControl, QUIButton, QUILoader, ERPEntityUtils, Grid, QUILocale, QUIAjax) {
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
            entityHash: false,
            hideUuids: []
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
                        header: QUILocale.get(lg, 'erp.process.status'),
                        dataIndex: 'status',
                        dataType: 'node',
                        width: 150,
                        className: 'grid-align-center'
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
                    }, {
                        header: QUILocale.get(lg, 'erp.process.paid_status'),
                        dataIndex: 'paid_status',
                        dataType: 'node',
                        width: 100,
                        className: 'grid-align-center'
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
                const hideUuids = this.getAttribute('hideUuids') || [];

                result.forEach((entry) => {
                    if (hideUuids.indexOf(entry.uuid) !== -1) {
                        return;
                    }

                    const Type = new QUIButton({
                        events: {
                            click: this.$click
                        },
                        icon: 'fa ' + window.ERP_ENTITY_ICONS[entry.entityType],
                        entityType: entry.entityType,
                        uuid: entry.uuid,
                        styles: {
                            width: 40
                        }
                    });

                    const Status = new Element('span', {
                        'class': 'processing-status',
                        text: entry.processing_status.title,
                        styles: {
                            color: entry.processing_status.color !== '---' ? entry.processing_status.color : '',
                            borderColor: entry.processing_status.color !== '---' ? entry.processing_status.color : ''
                        }
                    });

                    if (typeof entry.paid_status === 'undefined') {
                        entry.paid_status = 0;
                    }

                    const PaymentStatus = new Element('span', {
                        'class': 'payment-status payment-status-' + entry.paid_status,
                        html: QUILocale.get('quiqqer/erp', 'payment.status.' + entry.paid_status)
                    });

                    switch (entry.entityType) {
                        case 'QUI\\ERP\\Order\\Order':
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.order.open'));
                            break;

                        case 'QUI\\ERP\\Accounting\\Invoice\\Invoice':
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.invoice.open'));
                            break;

                        case 'QUI\\ERP\\Accounting\\Invoice\\InvoiceTemporary':
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.invoiceTemporary.open'));
                            break;

                        case 'QUI\\ERP\\SalesOrders\\SalesOrder':
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.salesOrder.open'));
                            break;

                        case 'QUI\\ERP\\Accounting\\Offers\\Offer':
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.offer.open'));
                            PaymentStatus.set('class', 'processing-status');
                            PaymentStatus.set('html', '---');
                            break;

                        case 'QUI\\ERP\\Accounting\\Offers\\OfferTemporary':
                            Type.setAttribute('title', QUILocale.get(lg, 'processGrid.temporaryOffer.open'));
                            PaymentStatus.set('class', 'processing-status');
                            PaymentStatus.set('html', '---');
                            break;
                    }


                    data.push({
                        type: Type,
                        status: Status,
                        paid_status: PaymentStatus,
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
            const uuid = Btn.getAttribute('uuid');
            const panel = ERPEntityUtils.getPanelByEntity(Btn.getAttribute('entityType'));

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
