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
    'package/quiqqer/countries/bin/Countries',
    'Users',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/DeliveryAddress.html'

], function (QUI, QUIControl, Countries, Users, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/DeliveryAddress',

        Binds: [
            '$onImport',
            '$checkBoxChange',
            '$onSelectChange',
            'isLoaded',
            '$onAddressInputChange'
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

            this.checked = false;
            this.$loaded = false;
            this.$userId = this.getAttribute('userId');

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

            this.$Addresses = Elm.getElement('[name="delivery-addresses"]');
            this.$Addresses.addEvent('change', this.$onSelectChange);

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

                // Add events
                Elm.getElements('.quiqqer-erp-delivery-address-field').addEvent('change', self.$onAddressInputChange);

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
            this.$Addresses.value = '';
            this.$onSelectChange();
        },

        /**
         * Set values
         *
         * @param {Object} value
         */
        setValue: function (value) {
            var self = this;

            if (typeOf(value) !== 'object') {
                return;
            }

            // Set address from address id OR custom input
            if ("id" in value && value.id) {
                this.$addressId = value.id;
            } else {
                this.$addressId = false;

                if ("company" in value) {
                    this.$Company.value = value.company;
                }

                if ("salutation" in value) {
                    this.$Salutation.value = value.salutation;
                }

                if ("firstname" in value) {
                    this.$Firstname.value = value.firstname;
                }

                if ("lastname" in value) {
                    this.$Lastname.value = value.company;
                }

                if ("street_no" in value) {
                    this.$Street.value = value.street_no;
                }

                if ("zip" in value) {
                    this.$ZIP.value = value.zip;
                }

                if ("city" in value) {
                    this.$City.value = value.city;
                }

                if ("country" in value) {
                    if (this.$loaded) {
                        this.$Country.value = value.country;
                    } else {
                        this.setAttribute('country', value.country);
                    }
                }
            }

            if ("uid" in value) {
                this.$userId = value.uid;

                this.loadAddresses().then(function () {
                    if (self.$addressId) {
                        self.$onSelectChange();
                    }
                }).catch(function () {
                    this.$Addresses.disabled = true;
                }.bind(this));
            }

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
         * Refresh the address list
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            this.$Addresses.set('html', '');

            if (!this.$userId) {
                this.$Company.value = '';
                this.$Street.value  = '';
                this.$ZIP.value     = '';
                this.$City.value    = '';
                this.$Country.value = '';

                this.$Salutation.value = '';
                this.$Firstname.value  = '';
                this.$Lastname.value   = '';

                return Promise.reject();
            }

            return this.loadAddresses().then(function () {
                console.log(111);
                //self.$onSelectChange();
            }).catch(function () {
                self.$Addresses.disabled = true;
            });
        },

        /**
         * Load the addresses
         */
        loadAddresses: function () {
            var self = this;

            this.$Addresses.set('html', '');
            this.$Addresses.disabled = true;

            if (!this.$userId) {
                return Promise.reject();
            }

            return this.getUser().then(function (User) {
                return User.getAddressList();
            }).then(function (addresses) {
                self.$Addresses.set('html', '');

                new Element('option', {
                    value       : '',
                    html        : QUILocale.get(lg, 'controls.DeliveryAddress.freeform_input'),
                    'data-value': ''
                }).inject(self.$Addresses);

                for (var i = 0, len = addresses.length; i < len; i++) {
                    new Element('option', {
                        value       : addresses[i].id,
                        html        : addresses[i].text,
                        'data-value': JSON.encode(addresses[i])
                    }).inject(self.$Addresses);
                }

                if (self.$addressId) {
                    self.$Addresses.value = self.$addressId;
                }

                self.$Addresses.disabled = false;
            }).catch(function (err) {
                console.error(err);
            });
        },

        /**
         * event : on select change
         */
        $onSelectChange: function () {
            var Select = this.$Addresses;

            var options = Select.getElements('option').filter(function (Option) {
                return Option.value === Select.value;
            });

            if (Select.value === '' || !options.length || options[0].get('data-value') === '') {
                this.$addressId = false;

                //this.$Company.value = '';
                //this.$Street.value  = '';
                //this.$ZIP.value     = '';
                //this.$City.value    = '';
                //this.$Country.value = '';
                //
                //this.$Salutation.value = '';
                //this.$Firstname.value  = '';
                //this.$Lastname.value   = '';
                return;
            }

            var data = JSON.decode(options[0].get('data-value'));

            this.$addressId     = data.id;
            this.$Company.value = data.company;
            this.$Street.value  = data.street_no;
            this.$ZIP.value     = data.zip;
            this.$City.value    = data.city;
            this.$Country.value = data.country;

            this.$Salutation.value = data.salutation;
            this.$Firstname.value  = data.firstname;
            this.$Lastname.value   = data.lastname;
        },

        /**
         * event: on attribute change
         *
         * @param {String} key
         * @param {String} value
         */
        $onSetAttribute: function (key, value) {
            var self = this;

            if (key === 'userId') {
                this.$userId = value;

                self.refresh().then(function () {
                    self.$Addresses.disabled = false;
                }).catch(function () {
                    self.$Addresses.disabled = true;
                });
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
            }

            if (!this.$userId) {
                Checkbox.checked = false;

                QUI.getMessageHandler().then(function (MH) {
                    MH.addInformation(
                        QUILocale.get('quiqqer/erp', 'message.select.customer'),
                        self.$Customer.getElm()
                    );
                });

                return;
            }

            if (Checkbox.checked) {
                closables.setStyle('display', null);
                this.loadAddresses();
                return;
            }

            closables.setStyle('display', 'none');
            this.clear();
        },

        /**
         * If an address line is manually changed.
         */
        $onAddressInputChange: function () {
            this.$Addresses.value = '';
            this.$addressId       = false;
        },

        /**
         * Check if control has finished loading
         *
         * @return {boolean}
         */
        isLoaded: function () {
            return this.$loaded;
        }
    });
});
