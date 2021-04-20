/**
 * Manage delivery address for customers / users
 *
 * Used in ERP document panels (invoice, offer, etc.)
 *
 * @module package/quiqqer/erp/bin/backend/controls/DeliveryAddress
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this] - Fires if control finished loading
 */
define('package/quiqqer/erp/bin/backend/controls/DeliveryAddress', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'package/quiqqer/countries/bin/Countries',
    'Users',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/DeliveryAddress.html',
    'css!package/quiqqer/erp/bin/backend/controls/DeliveryAddress.css'

], function (QUI, QUIControl, QUIButton, Countries, Users, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/DeliveryAddress',

        Binds: [
            '$onImport',
            '$checkBoxChange',
            '$displayAddressData',
            'isLoaded',
            '$onClickSelectAddress'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Addresses = null;
            this.$Company   = null;
            this.$Street    = null;
            this.$ZIP       = null;
            this.$City      = null;
            this.$Country   = null;

            this.$Salutation = null;
            this.$Firstname  = null;
            this.$Lastname   = null;

            this.checked           = false;
            this.$loaded           = false;
            this.$userId           = this.getAttribute('userId');
            this.$AddressSelectBtn = null;

            this.addEvents({
                onImport      : this.$onImport,
                onInject      : this.$onInject,
                onSetAttribute: this.$onSetAttribute
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();

            Elm.set('html', Mustache.render(template, {
                labelDifferentDeliveryAddress: QUILocale.get(lg, 'control.DeliveryAddress.tpl.labelDifferentDeliveryAddress'),
                textAddresses                : QUILocale.get('quiqqer/quiqqer', 'address'),
                textCompany                  : QUILocale.get('quiqqer/quiqqer', 'company'),
                textStreet                   : QUILocale.get('quiqqer/quiqqer', 'street_no'),
                textZip                      : QUILocale.get('quiqqer/quiqqer', 'zip'),
                textCity                     : QUILocale.get('quiqqer/quiqqer', 'city'),
                textCountry                  : QUILocale.get('quiqqer/quiqqer', 'country'),
                textSalutation               : QUILocale.get('quiqqer/quiqqer', 'salutation'),
                textFirstname                : QUILocale.get('quiqqer/quiqqer', 'firstname'),
                textLastname                 : QUILocale.get('quiqqer/quiqqer', 'lastname')
            }));

            // Address select
            this.$AddressSelectBtn = new QUIButton({
                'class'  : 'quiqqer-erp-delivery-address-select-btn',
                text     : QUILocale.get(lg, 'controls.DeliveryAddress.btn.select.text'),
                textimage: 'fa fa-address-book-o',
                disabled : true,
                events   : {
                    onClick: this.$onClickSelectAddress
                }
            }).inject(
                Elm.getElement('.quiqqer-erp-delivery-address-select')
            );

            // changeable
            this.$Checked = Elm.getElement('[name="differentDeliveryAddress"]');
            this.$Checked.addEvent('change', this.$checkBoxChange);

            // address stuff
            this.$addressId = false;
            this.$Company   = Elm.getElement('[name="delivery-company"]');
            this.$Street    = Elm.getElement('[name="delivery-street_no"]');
            this.$ZIP       = Elm.getElement('[name="delivery-zip"]');
            this.$City      = Elm.getElement('[name="delivery-city"]');
            this.$Country   = Elm.getElement('[name="delivery-country"]');

            this.$Salutation = Elm.getElement('[name="delivery-salutation"]');
            this.$Firstname  = Elm.getElement('[name="delivery-firstname"]');
            this.$Lastname   = Elm.getElement('[name="delivery-lastname"]');

            this.$Company.disabled = false;
            this.$Street.disabled  = false;
            this.$ZIP.disabled     = false;
            this.$City.disabled    = false;

            this.$Salutation.disabled = false;
            this.$Firstname.disabled  = false;
            this.$Lastname.disabled   = false;

            var Panel = QUI.Controls.getById(
                this.getElm().getParent('.qui-panel').get('data-quiid')
            );

            this.$Customer = QUI.Controls.getById(
                Panel.getElm().getElement('[name="customer"]').get('data-quiid')
            );

            if (this.$Customer) {
                this.$userId = this.$Customer.getValue();
            }

            Countries.getCountries().then(function (result) {
                new Element('option', {
                    value: '',
                    html : ''
                }).inject(self.$Country);

                for (var code in result) {
                    if (!result.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html : result[code]
                    }).inject(self.$Country);
                }

                if (self.getAttribute('country')) {
                    self.$Country.value = self.getAttribute('country');
                }

                self.$Country.disabled = false;
                self.$loaded           = true;

                self.fireEvent('loaded', [self]);
            });
        },

        $onInject: function () {
            this.$onImport();
        },

        /**
         * Return the current value
         *
         * @return {{zip, uid: *, country, firstname, city, street_no, company, id: (boolean|*), salutation, lastname}|null}
         */
        getValue: function () {
            if (!this.$Checked.checked) {
                return null;
            }

            return {
                uid       : this.$userId,
                id        : this.$addressId,
                company   : this.$Company.value,
                salutation: this.$Salutation.value,
                firstname : this.$Firstname.value,
                lastname  : this.$Lastname.value,
                street_no : this.$Street.value,
                zip       : this.$ZIP.value,
                city      : this.$City.value,
                country   : this.$Country.value
            };
        },

        /**
         * Clears the selection - no address are selected
         */
        clear: function () {
            this.$displayAddressData({
                company   : '',
                street_no : '',
                zip       : '',
                city      : '',
                country   : '',
                salutation: '',
                firstname : '',
                lastname  : '',
            });
        },

        /**
         * Set values
         *
         * @param {Object} value
         */
        setValue: function (value) {
            if (typeOf(value) !== 'object') {
                return;
            }

            // Set address from address id OR custom input
            if ("id" in value && value.id) {
                this.$addressId = value.id;
            } else {
                this.$addressId = false;
            }

            if ("uid" in value) {
                this.$userId = value.uid;
            }

            var Address = {
                company   : '',
                street_no : '',
                zip       : '',
                city      : '',
                country   : '',
                salutation: '',
                firstname : '',
                lastname  : '',
            };

            Object.merge(Address, value);
            this.$displayAddressData(Address);

            this.$Checked.checked = true;
            this.$checkBoxChange();
        },

        /**
         * Return the selected user
         *
         * @return {Promise}
         */
        getUser: function () {
            if (!this.$userId) {
                return Promise.reject('No User-ID');
            }

            var User = Users.get(this.$userId);

            if (User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         * event : on select change
         *
         * @package {Object} Address
         */
        $displayAddressData: function (Address) {
            this.$Company.value = Address.company;
            this.$Street.value  = Address.street_no;
            this.$ZIP.value     = Address.zip;
            this.$City.value    = Address.city;

            this.$Salutation.value = Address.salutation;
            this.$Firstname.value  = Address.firstname;
            this.$Lastname.value   = Address.lastname;

            this.$Country.value = Address.country;
            this.setAttribute('country', Address.country);
        },

        /**
         * event: on attribute change
         *
         * @param {String} key
         * @param {String} value
         */
        $onSetAttribute: function (key, value) {
            if (key === 'userId') {
                this.$userId = value;
                this.$AddressSelectBtn.enable();
            }
        },

        /**
         * event if the check box changes
         *
         * @param event
         */
        $checkBoxChange: function (event) {
            var self      = this,
                Checkbox  = this.getElm().getElement('[name="differentDeliveryAddress"]'),
                closables = this.getElm().getElements('.closable');

            if (event) {
                event.stop();
            }

            if (!Checkbox) {
                return;
            }

            if (!this.$userId) {
                var Panel = QUI.Controls.getById(
                    this.getElm().getParent('.qui-panel').get('data-quiid')
                );

                this.$Customer = QUI.Controls.getById(
                    Panel.getElm().getElement('[name="customer"]').get('data-quiid')
                );

                if (this.$Customer) {
                    this.$userId = this.$Customer.getValue();
                }

                Checkbox.checked = false;

                QUI.getMessageHandler().then(function (MH) {
                    MH.addInformation(
                        QUILocale.get('quiqqer/erp', 'message.select.customer'),
                        self.$Customer.getElm()
                    );
                });

                this.$AddressSelectBtn.disable();

                return;
            }

            this.$AddressSelectBtn.enable();

            if (Checkbox.checked) {
                closables.setStyle('display', null);
                return;
            }

            this.$AddressSelectBtn.disable();

            closables.setStyle('display', 'none');
            this.clear();
        },

        /**
         * Check if control has finished loading
         *
         * @return {boolean}
         */
        isLoaded: function () {
            return this.$loaded;
        },

        /**
         * Select customer address to pre-fill address fields
         */
        $onClickSelectAddress: function () {
            var self = this;

            if (!this.$userId) {
                return;
            }

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/address/Window'
            ], function (Win) {
                new Win({
                    userId   : self.$userId,
                    autoclose: false,

                    events: {
                        onSubmit: function (Win, addressId) {
                            Win.Loader.show();

                            self.getUser().then(function (User) {
                                return User.getAddressList();
                            }).then(function (addresses) {
                                for (var i = 0, len = addresses.length; i < len; i++) {
                                    if (addresses[i].id === addressId) {
                                        self.$displayAddressData(addresses[i]);
                                        break;
                                    }
                                }

                                Win.close();
                            }).catch(function () {
                                Win.Loader.hide();
                            });

                            Win.close();
                        }
                    }
                }).open();
            });
        }
    });
});
