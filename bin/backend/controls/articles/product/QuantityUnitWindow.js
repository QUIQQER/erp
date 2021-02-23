/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/product/QuantityUnitWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/articles/product/QuantityUnitWindow', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Ajax',
    'Locale',

    'css!package/quiqqer/erp/bin/backend/controls/articles/product/QuantityUnitWindow.css'

], function (QUI, QUIControl, QUIConfirm, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/product/QuantityUnitWindow',

        options: {
            title: QUILocale.get(lg, 'controls.QuantityUnitWindow.title'),
            icon : 'fa fa-balance-scale'
        },

        Binds: [
            '$onOpen',
            '$click'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                maxHeight: 600,
                maxWidth : 400
            });

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this;

            this.getContent().set('html', '');
            this.Loader.show();

            QUIAjax.get('package_quiqqer_erp_ajax_products_getQuantityUnitList', function (UnitList) {
                var Content = self.getContent(),
                    current = QUILocale.getCurrent();

                var i, title, Node;

                for (i in UnitList) {
                    if (!UnitList.hasOwnProperty(i)) {
                        continue;
                    }

                    if (typeof UnitList[i].title[current] !== 'undefined') {
                        title = UnitList[i].title[current];
                    } else {
                        title = UnitList[Object.keys(UnitList)[0]];
                    }

                    Node = new Element('div', {
                        'class'     : 'quiqqer-erp-quantity-unit-entry',
                        html        : title,
                        'data-index': i,
                        events      : {
                            click: self.$click
                        }
                    }).inject(Content);

                    if (UnitList[i].default) {
                        Node.addClass('quiqqer-erp-quantity-unit-entry--active');
                    }
                }

                Content.set('tabindex', -1);

                Content.addEvents({
                    keydown: function (event) {
                        var ActiveNode = Content.getElement('.quiqqer-erp-quantity-unit-entry--active');

                        if (event.key === 'up') {
                            var PreviousNode = ActiveNode.getPrevious('.quiqqer-erp-quantity-unit-entry');

                            if (PreviousNode) {
                                PreviousNode.click();
                            }

                            return;
                        }

                        if (event.key === 'down') {
                            var NextNode = ActiveNode.getNext('.quiqqer-erp-quantity-unit-entry');

                            if (NextNode) {
                                NextNode.click();
                            }

                            return;
                        }

                        if (event.key === 'enter') {
                            self.submit();
                        }
                    },
                });

                Content.focus();

                self.Loader.hide();
            }, {
                'package': 'quiqqer/erp'
            });
        },

        /**
         * click event
         *
         * @param e
         */
        $click: function (e) {
            var Target  = e.target;
            var Content = this.getContent();

            Content.getElements('.quiqqer-erp-quantity-unit-entry--active')
                .removeClass('quiqqer-erp-quantity-unit-entry--active');


            if (!Target.hasClass('quiqqer-erp-quantity-unit-entry')) {
                Target = Target.getParent('.quiqqer-erp-quantity-unit-entry');
            }

            Target.addClass('quiqqer-erp-quantity-unit-entry--active');
        },

        /**
         *
         */
        submit: function () {
            var value   = '',
                title   = '',
                Content = this.getContent(),
                Active  = Content.getElement('.quiqqer-erp-quantity-unit-entry--active');

            if (Active) {
                value = Active.get('data-index');
                title = Active.get('html').trim();
            }

            this.fireEvent('submit', [this, value, title]);

            if (this.getAttribute('autoclose')) {
                this.close();
            }
        }
    });
});
