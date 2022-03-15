/**
 * Window for selecting user email addresses.
 *
 * @module package/quiqqer/erp/bin/backend/controls/userData/ContactEmailSelectWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSubmit [selectedEmailAddress, this]
 */
define('package/quiqqer/erp/bin/backend/controls/userData/ContactEmailSelectWindow', [

    'qui/controls/windows/Confirm',
    'Locale',
    'Ajax'

], function (QUIConfirm, QUILocale, QUIAjax) {
    "use strict";

    const pkg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/erp/bin/backend/controls/userData/ContactEmailSelectWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            userId   : false,
            icon     : 'fa fa-at',
            title    : QUILocale.get(pkg, 'ContactEmailSelectWindow.title'),
            maxHeight: 300,
            maxWidth : 550,
            autoclose: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Select = [];

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        $onOpen: function (Win) {
            Win.Loader.show();

            Win.getContent()
                .set('html', QUILocale.get(pkg, 'ContactEmailSelectWindow.information'));

            this.$Select = new Element('select', {
                styles: {
                    display: 'block',
                    clear  : 'both',
                    margin : '1rem auto 0',
                    width  : 500
                }
            }).inject(Win.getContent());

            this.$getUserEmailAddresses().then((emailAddresses) => {
                emailAddresses.forEach((emailAddress) => {
                    new Element('option', {
                        value: emailAddress,
                        html : emailAddress
                    }).inject(this.$Select);
                });

                Win.Loader.hide();
            });
        },

        /**
         * Return the loaded user object
         *
         * @return {Promise<Array>}
         */
        $getUserEmailAddresses: function () {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_userData_getUserEmailAddresses', resolve, {
                    'package': pkg,
                    userId   : this.getAttribute('userId'),
                    onError  : reject
                });
            });
        },

        /**
         * Submit the window
         *
         * @method qui/controls/windows/Confirm#submit
         */
        submit: function () {
            this.fireEvent('submit', [this.$Select.value, this]);

            if (this.getAttribute('autoclose')) {
                this.close();
            }
        }
    });
});
