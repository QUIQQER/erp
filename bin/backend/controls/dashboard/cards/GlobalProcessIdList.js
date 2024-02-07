/**
 * @module package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList', [

    'qui/QUI',
    'package/quiqqer/dashboard/bin/backend/controls/Card',
    'controls/grid/Grid',
    'Locale',
    'Ajax'

], function(QUI, Card, Grid, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: Card,
        Type: 'package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList',

        Binds: [
            'refresh',
            '$onCreate',
            '$onDblClick',
            '$getPlugins'
        ],

        initialize: function(options) {
            this.parent(options);

            this.setAttribute({
                content: '',
                priority: 1
            });

            this.$Grid = null;

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        $onCreate: function() {
            this.$Content.addClass('card-table');
            this.$Content.removeClass('card-body');

            this.getElm().classList.add('col-sg-12');
            this.getElm().classList.add('col-sm-12');

            this.setTitle(QUILocale.get(lg, 'dashboard.erp.grid.title'));
            this.setContent('');

            const Container = new Element('div', {
                styles: {
                    height: 600,
                    width: '100%'
                }
            }).inject(this.getContent());

            this.$getPlugins().then((plugins) => {
                const columnModel = [];

                console.log(plugins);

                columnModel.push({
                    header: QUILocale.get(lg, 'dashboard.erp.date'),
                    dataIndex: 'date',
                    dataType: 'string',
                    width: 140
                });

                columnModel.push({
                    header: QUILocale.get(lg, 'dashboard.erp.processId'),
                    dataIndex: 'globalProcessId',
                    dataType: 'string',
                    width: 240
                });

                if (plugins.indexOf('quiqqer/invoice') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.invoice'),
                        dataIndex: 'invoice',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/order') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.order'),
                        dataIndex: 'order',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/offers') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.offer'),
                        dataIndex: 'offer',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/salesorders') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.salesOrder'),
                        dataIndex: 'salesorders',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/purchasing') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.purchasing'),
                        dataIndex: 'purchasing',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/booking') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.booking'),
                        dataIndex: 'booking',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/contract') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.contract'),
                        dataIndex: 'contract',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/payments') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.payments'),
                        dataIndex: 'payments',
                        dataType: 'string',
                        width: 240
                    });
                }

                if (plugins.indexOf('quiqqer/delivery-notes') !== -1) {
                    columnModel.push({
                        header: QUILocale.get(lg, 'dashboard.erp.deliveryNotes'),
                        dataIndex: 'deliveryNotes',
                        dataType: 'string',
                        width: 240
                    });
                }

                this.$Grid = new Grid(Container, {
                    buttons: [
                        {
                            name: 'add',
                            textimage: 'fa fa-plus',
                            text: 'Vorgang hinzufügen',
                            styles: {
                                'float': 'right'
                            }
                        }
                    ],
                    columnModel: columnModel,
                    pagination: true,
                    exportData: true
                });

                this.$Grid.addEvents({
                    onRefresh: this.refresh,
                    onDblClick: this.$onDblClick
                });

                this.$Content.setStyle('padding', 10);
                this.$Content.setStyle('display', null);

                this.$Grid.setHeight(600);
                this.refresh();
            });
        },

        refresh: function() {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            this.$Grid.showLoader();

            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_erp_ajax_dashboard_globalProcess_getList', (result) => {
                    let entry;
                    const data = [];
                    const DateFormatter = QUILocale.getDateTimeFormatter({
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });

                    for (let hash in result) {
                        entry = result[hash];
                        entry.globalProcessId = hash;
                        entry.date = DateFormatter.format(new Date(entry.date));
                        data.push(entry);
                    }

                    this.$Grid.setData({
                        data: data
                    });

                    console.log(result);
                    this.$Grid.hideLoader();
                    resolve(result);
                }, {
                    'package': 'quiqqer/erp'
                });
            });
        },

        $getPlugins: function() {
            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_erp_ajax_dashboard_globalProcess_availablePlugins', resolve, {
                    'package': 'quiqqer/erp'
                });
            });
        },

        $onDblClick: function() {
            const selected = this.$Grid.getSelectedData();
            const globalProcessId = selected[0].globalProcessId;

            window.parent.require([
                'utils/Panels',
                'package/quiqqer/erp/bin/backend/controls/process/ProcessPanel'
            ], (PanelUtils, ProcessPanel) => {
                PanelUtils.openPanelInTasks(
                    new ProcessPanel({
                        globalProcessId: globalProcessId
                    })
                );
            });
        }
    });
});
