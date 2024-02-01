/**
 * @module package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList.css'

], function(QUI, QUIControl, QUILocale, QUIAjax, Mustache, template) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList',

        Binds: [
            '$onCreate'
        ],

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        $onCreate: function() {
            this.$Content.addClass('card-table');
            this.$Content.removeClass('card-body');

            this.getElm().classList.add('col-sg-12');
            this.getElm().classList.add('col-sm-12');

            this.setAttribute({
                head_title: QUILocale.get(lg, 'ecoyn.last.orders')
            });

            this.setContent(Mustache.render(template, {
                globalProcessId: QUILocale.get(lg, 'dashboard.erp.processId'),
                title: QUILocale.get(lg, 'dashboard.erp.title'),
                date: QUILocale.get(lg, 'dashboard.erp.date'),
                invoice: QUILocale.get(lg, 'dashboard.erp.invoice'),
                order: QUILocale.get(lg, 'dashboard.erp.order'),
                offer: QUILocale.get(lg, 'dashboard.erp.offer'),
                salesOrder: QUILocale.get(lg, 'dashboard.erp.salesOrder'),
                purchasing: QUILocale.get(lg, 'dashboard.erp.purchasing'),
                booking: QUILocale.get(lg, 'dashboard.erp.booking'),
                contract: QUILocale.get(lg, 'dashboard.erp.contract'),
                payments: QUILocale.get(lg, 'dashboard.erp.payments'),
                deliveryNotes: QUILocale.get(lg, 'dashboard.erp.deliveryNotes')
            }));
        }

    });
});