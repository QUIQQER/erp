document.addEvent('domready', function () {
    "use strict";

    if (QUIQQER_USER.id) {
        return;
    }

    require([
        'qui/controls/windows/Popup',
        'utils/Session',
        'Ajax',
        'Locale'
    ], function (QUIWindow, Session, QUIAjax, QUILocale) {
        Session.get('quiqqer.erp.b2b.status').then(function (data) {
            if (data !== null) {
                return;
            }

            // open b2b b2c window if active
            QUIAjax.get('package_quiqqer_erp_ajax_frontend_showB2BB2CWindow', function (result) {
                if (!result) {
                    return;
                }

                var lg = 'quiqqer/erp';

                new QUIWindow({
                    title    : false,
                    maxHeight: 400,
                    maxWidth : 600,
                    buttons  : false,
                    events   : {
                        onOpen: function (Win) {
                            Win.Loader.show();

                            require(['css!package/quiqqer/erp/bin/frontend.css'], function () {
                                Win.getContent().addClass('quiqqer-erp-customer-request');
                                Win.getContent().set(
                                    'html',
                                    '<div class="quiqqer-erp-customer-request-text">' +
                                    '   ' + QUILocale.get(lg, 'menu.erp.b2cb2bFrontendWindow.frontend.text') +
                                    '</div>'
                                );

                                var Buttons = new Element('div', {
                                    'class': 'quiqqer-erp-customer-request-buttons'
                                }).inject(Win.getContent());

                                new Element('button', {
                                    html  : '' +
                                        '<span class="quiqqer-erp-customer-request-answer">' +
                                        '   ' + QUILocale.get(lg, 'menu.erp.b2cb2bFrontendWindow.frontend.yes') +
                                        '</span>' +
                                        '<span class="quiqqer-erp-customer-request-desc">' +
                                        '   ' + QUILocale.get(lg, 'menu.erp.b2cb2bFrontendWindow.frontend.yes.desc') +
                                        '</span>',
                                    events: {
                                        click: function () {
                                            Win.Loader.show();

                                            Session.set('quiqqer.erp.b2b.status', 1).then(function () {
                                                Win.close();
                                            });
                                        }
                                    }
                                }).inject(Buttons);

                                new Element('button', {
                                    html  : '' +
                                        '<span class="quiqqer-erp-customer-request-answer">' +
                                        '   ' + QUILocale.get(lg, 'menu.erp.b2cb2bFrontendWindow.frontend.no') +
                                        '</span>' +
                                        '<span class="quiqqer-erp-customer-request-desc">' +
                                        '   ' + QUILocale.get(lg, 'menu.erp.b2cb2bFrontendWindow.frontend.no.desc') +
                                        '</span>',
                                    events: {
                                        click: function () {
                                            Win.Loader.show();

                                            Session.set('quiqqer.erp.b2b.status', 2).then(function () {
                                                Win.close();
                                            });
                                        }
                                    }
                                }).inject(Buttons);

                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            }, {
                'package': 'quiqqer/erp'
            });
        });
    });
});
