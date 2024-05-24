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
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/DeliveryAddress.html',
    'css!package/quiqqer/erp/bin/backend/controls/DeliveryAddress.css'

], function(QUI, QUIControl, QUIButton, Countries, Users, QUIAjax, QUILocale, Mustache, template) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/DeliveryAddress',

        Binds: [
            '$onImport',
            '$checkBoxChange',
            '$displayAddressData',
            'isLoaded',
            '$onClickSelectAddress',
            'clear',
            'reset'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Addresses = null;
            this.$Company = null;
            this.$Street = null;
            this.$ZIP = null;
            this.$City = null;
            this.$Country = null;

            this.$Salutation = null;
            this.$Firstname = null;
            this.$Lastname = null;

            this.checked = false;
            this.$loaded = false;
            this.$userId = this.getAttribute('userId');
            this.$AddressSelectBtn = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject,
                onSetAttribute: this.$onSetAttribute
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            const self = this,
                Elm = this.getElm();

            function ignoreAutoFill(node)
            {
                node.role = 'presentation';
                node.autocomplete = 'off';
            }

            Elm.set('html', Mustache.render(template, {
                labelDifferentDeliveryAddress: QUILocale.get(
                    lg,
                    'control.DeliveryAddress.tpl.labelDifferentDeliveryAddress'
                ),
                textAddresses: QUILocale.get('quiqqer/core', 'address'),
                textCompany: QUILocale.get('quiqqer/core', 'company'),
                textStreet: QUILocale.get('quiqqer/core', 'street_no'),
                textZip: QUILocale.get('quiqqer/core', 'zip'),
                textCity: QUILocale.get('quiqqer/core', 'city'),
                textCountry: QUILocale.get('quiqqer/core', 'country'),
                textSalutation: QUILocale.get('quiqqer/core', 'salutation'),
                textFirstname: QUILocale.get('quiqqer/core', 'firstname'),
                textLastname: QUILocale.get('quiqqer/core', 'lastname')
            }));

            // Address select
            this.$AddressSelectBtn = new QUIButton({
                'class': 'quiqqer-erp-delivery-address-select-btn',
                text: QUILocale.get(lg, 'controls.DeliveryAddress.btn.select.text'),
                textimage: 'fa fa-address-book-o',
                disabled: true,
                events: {
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
            this.$Company = Elm.getElement('[name="delivery-company"]');
            this.$Street = Elm.getElement('[name="delivery-street_no"]');
            this.$ZIP = Elm.getElement('[name="delivery-zip"]');
            this.$City = Elm.getElement('[name="delivery-city"]');
            this.$Country = Elm.getElement('[name="delivery-country"]');

            this.$Salutation = Elm.getElement('[name="delivery-salutation"]');
            this.$Firstname = Elm.getElement('[name="delivery-firstname"]');
            this.$Lastname = Elm.getElement('[name="delivery-lastname"]');

            this.$Company.disabled = false;
            this.$Street.disabled = false;
            this.$ZIP.disabled = false;
            this.$City.disabled = false;

            this.$Salutation.disabled = false;
            this.$Firstname.disabled = false;
            this.$Lastname.disabled = false;

            ignoreAutoFill(this.$Salutation);
            ignoreAutoFill(this.$Firstname);
            ignoreAutoFill(this.$Lastname);
            ignoreAutoFill(this.$Company);
            ignoreAutoFill(this.$Street);
            ignoreAutoFill(this.$ZIP);
            ignoreAutoFill(this.$City);

            const Panel = QUI.Controls.getById(
                this.getElm().getParent('.qui-panel').get('data-quiid')
            );

            const CustomerNode = Panel.getElm().getElement('[name="customer"]');

            if (CustomerNode) {
                let loadCustomer = Promise.resolve();

                if (CustomerNode.get('data-quiid')) {
                    this.$Customer = QUI.Controls.getById(CustomerNode.get('data-quiid'));
                } else {
                    loadCustomer = new Promise((resolve) => {
                        CustomerNode.addEvent('load', () => {
                            this.$Customer = QUI.Controls.getById(CustomerNode.get('data-quiid'));
                            resolve();
                        });
                    });
                }

                loadCustomer.then(() => {
                    if (!this.$Customer) {
                        return;
                    }

                    this.$userId = this.$Customer.getValue();

                    this.$Customer.addEvent('onChange', () => {
                        // same user needs no change
                        if (this.$Customer.getValue() === this.$userId) {
                            return;
                        }

                        this.$userId = this.$Customer.getValue();

                        this.$getDeliveryAddressFromUser().then((result) => {
                            if (!result) {
                                this.$Checked.checked = false;
                                this.$checkBoxChange();
                                return;
                            }

                            //this.$Checked.checked = true;
                            this.setValue(result);
                        }).catch((err) => {
                            console.error(err);
                            this.$Checked.checked = false;
                            this.$checkBoxChange();
                        });
                    });
                });
            }

            Countries.getCountries().then(function(result) {
                new Element('option', {
                    value: '',
                    html: ''
                }).inject(self.$Country);

                for (let code in result) {
                    if (!result.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html: result[code]
                    }).inject(self.$Country);
                }

                if (self.getAttribute('country')) {
                    self.$Country.value = self.getAttribute('country');
                }

                self.$Country.disabled = false;
                self.$loaded = true;

                self.fireEvent('loaded', [self]);
            });
        },

        $onInject: function() {
            this.$onImport();
        },

        $getDeliveryAddressFromUser: function() {
            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_erp_ajax_userData_getDeliveryAddress', resolve, {
                    'package': 'quiqqer/erp',
                    userId: this.$userId
                });
            });
        },

        /**
         * Return the current value
         *
         * @return {{zip, uid: *, country, firstname, city, street_no, company, id: (boolean|*), salutation, lastname}|null}
         */
        getValue: function() {
            if (!this.$Checked.checked) {
                return null;
            }

            return {
                //uid       : this.$userId,
                //id        : this.$addressId,
                company: this.$Company.value,
                salutation: this.$Salutation.value,
                firstname: this.$Firstname.value,
                lastname: this.$Lastname.value,
                street_no: this.$Street.value,
                zip: this.$ZIP.value,
                city: this.$City.value,
                country: this.$Country.value
            };
        },

        /**
         * Clears the selection - no address are selected
         */
        clear: function() {
            this.$displayAddressData({
                company: '',
                street_no: '',
                zip: '',
                city: '',
                country: '',
                salutation: '',
                firstname: '',
                lastname: ''
            });
        },

        /**
         * Reset control
         */
        reset: function() {
            this.clear();
            this.$Checked.checked = false;
            this.$checkBoxChange();
        },

        /**
         * Set values
         *
         * @param {Object} value
         */
        setValue: function(value) {
            if (typeOf(value) !== 'object') {
                return;
            }

            // Set address from address id OR custom input
            if ('id' in value && value.id) {
                this.$addressId = value.id;
            } else {
                this.$addressId = false;
            }

            if ('uid' in value) {
                this.$userId = value.uid;
            }

            const Address = {
                company: '',
                street_no: '',
                zip: '',
                city: '',
                country: '',
                salutation: '',
                firstname: '',
                lastname: ''
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
        getUser: function() {
            if (!this.$userId) {
                return Promise.reject('No User-ID');
            }

            const User = Users.get(this.$userId);

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
        $displayAddressData: function(Address) {
            this.$Company.value = Address.company;
            this.$Street.value = Address.street_no;
            this.$ZIP.value = Address.zip;
            this.$City.value = Address.city;

            this.$Salutation.value = Address.salutation;
            this.$Firstname.value = Address.firstname;
            this.$Lastname.value = Address.lastname;

            this.$Country.value = Address.country;
            this.setAttribute('country', Address.country);
        },

        /**
         * event: on attribute change
         *
         * @param {String} key
         * @param {String} value
         */
        $onSetAttribute: function(key, value) {
            if (key === 'userId') {
                this.$userId = value;

                if (this.$userId) {
                    this.$AddressSelectBtn.enable();
                } else {
                    this.$AddressSelectBtn.disable();
                }
            }
        },

        /**
         * event if the checkbox changes
         *
         * @param {DocumentEvent} [event]
         */
        $checkBoxChange: function(event) {
            const closables = this.getElm().getElements('.closable');

            if (event) {
                event.stop();
            }

            if (!this.$Checked) {
                return;
            }

            if (!this.$userId) {
                const Panel = QUI.Controls.getById(
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
                this.$Checked.checked = false;

                this.$AddressSelectBtn.disable();
                return;
            }

            this.$AddressSelectBtn.enable();

            if (this.$Checked.checked) {
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
        isLoaded: function() {
            return this.$loaded;
        },

        /**
         * Select customer address to pre-fill address fields
         */
        $onClickSelectAddress: function() {
            const self = this;

            if (!this.$userId) {
                return;
            }

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/address/Window'
            ], function(Win) {
                new Win({
                    userId: self.$userId,
                    autoclose: false,

                    events: {
                        onSubmit: function(Win, addressId) {
                            Win.Loader.show();

                            self.getUser().then(function(User) {
                                return User.getAddressList();
                            }).then(function(addresses) {
                                for (let i = 0, len = addresses.length; i < len; i++) {
                                    if (addresses[i].id === addressId) {
                                        self.$displayAddressData(addresses[i]);
                                        break;
                                    }
                                }

                                Win.close();
                            }).catch(function() {
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
