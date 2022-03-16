/**
 * Control for selecting a user / address / contact data for ERP entities (e.g. invoice customer).
 *
 * @module package/quiqqer/erp/bin/backend/controls/userData/UserData
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onChange [userData, this]
 */
define('package/quiqqer/erp/bin/backend/controls/userData/UserData', [

    'qui/QUI',
    'qui/controls/Control',

    'package/quiqqer/customer/bin/backend/controls/customer/address/Window',
    'package/quiqqer/erp/bin/backend/controls/userData/ContactEmailSelectWindow',

    'Users',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/userData/UserData.html',
    'css!package/quiqqer/erp/bin/backend/controls/userData/UserData.css'

], function (QUI, QUIControl, AddressWindow, ContactEmailSelectWindow, Users, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    const pkg = 'quiqqer/erp';

    const fields = [
        'userId',
        'addressId',
        'contactPerson',
        'contactEmail',

        'suffix',
        'firstname',
        'lastname',
        'name',
        'company',
        'street_no',
        'zip',
        'city',
        'country',

        'isCommercial'
    ];

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/userData/UserData',

        Binds: [
            'toggleExtras',
            'editCustomer',
            '$onInject',
            '$fireChange'
        ],

        options: {
            userId   : false,
            addressId: false,

            editContactPerson: true,
            editContactEmail : true,

            name         : '',
            contactPerson: '',
            contactEmail : '',
            company      : false,
            street_no    : false,
            zip          : false,
            city         : false,

            labelUser              : QUILocale.get(pkg, 'UserData.tpl.labelCustomer'),
            userSelectWindowControl: 'package/quiqqer/customer/bin/backend/controls/customer/Select',
            userPanelControl       : 'package/quiqqer/customer/bin/backend/Handler'
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$CustomerSelect         = null;
            this.$ContactPerson          = null;
            this.$BtnContactPersonSelect = null;

            this.$ContactEmail          = null;
            this.$BtnContactEmailSelect = null;
            this.$RowContactPerson      = null;

            this.$Extras       = null;
            this.$Company      = null;
            this.$Street       = null;
            this.$Zip          = null;
            this.$City         = null;
            this.$Table        = null;
            this.$AddressRow   = null;
            this.$AddressField = null;

            this.$rows          = [];
            this.$extrasAreOpen = false;
            this.$oldUserId     = false;

            this.$loading   = true;
            this.$setValues = false;

            this.$Panel = null;
        },

        /**
         * Create the DOMNoe Element
         * @returns {*}
         */
        create: function () {
            const labelUser = this.getAttribute('labelUser');

            this.$Elm = new Element('div', {
                html: Mustache.render(template, {
                    labelTitle              : labelUser,
                    labelCustomer           : labelUser,
                    labelAddress            : QUILocale.get('quiqqer/quiqqer', 'address'),
                    labelCompany            : QUILocale.get('quiqqer/quiqqer', 'company'),
                    labelStreet             : QUILocale.get('quiqqer/quiqqer', 'street'),
                    labelZip                : QUILocale.get('quiqqer/quiqqer', 'zip'),
                    labelCity               : QUILocale.get('quiqqer/quiqqer', 'city'),
                    labelExtra              : QUILocale.get(pkg, 'UserData.btn.extra'),
                    labelUserEdit           : QUILocale.get(pkg, 'UserData.btn.userEdit', {labelUser}),
                    labelContactPerson      : QUILocale.get(pkg, 'UserData.tpl.labelContactPerson'),
                    labelContactEmail       : QUILocale.get(pkg, 'UserData.tpl.labelContactEmail'),
                    placeholderContactPerson: QUILocale.get(pkg, 'UserData.tpl.placeholderContactPerson'),
                    placeholderContactEmail : QUILocale.get(pkg, 'UserData.tpl.placeholderContactEmail'),

                    userSelectWindowControl: this.getAttribute('userSelectWindowControl'),
                })
            });

            // Extras (show address fields)
            this.$Extras = this.$Elm.getElement('.quiqqer-erp-userdata-address-opener');
            this.$Extras.addEvent('click', this.toggleExtras);

            // Customer edit (button that opens customer / user panel)
            this.$CustomerEdit = this.$Elm.getElement('.quiqqer-erp-userdata-address-userEdit');
            this.$CustomerEdit.addEvent('click', this.editCustomer);

            // Contact person
            this.$RowContactPerson       = this.$Elm.getElement('.quiqqer-erp-userdata-row-contactPerson');
            this.$ContactPerson          = this.$Elm.getElement('input[name="contact_person"]');
            this.$BtnContactPersonSelect = this.$Elm.getElement('button[name="select-contact-id-address"]');

            this.$ContactPerson.addEvent('keyup', () => {
                this.setAttribute('contactPerson', this.$ContactPerson.value);
                this.$fireChange();
            });

            this.$BtnContactPersonSelect.addEvent('click', () => {
                new AddressWindow({
                    autoclose: false,
                    userId   : this.getAttribute('userId'),
                    events   : {
                        onSubmit: (Win, addressId, address) => {
                            Win.close();

                            this.$setContactPersonByAddress(address);
                            this.$fireChange();
                        }
                    }
                }).open();
            });

            // Contact email
            this.$ContactEmail = this.$Elm.getElement('input[name="contact_email"]');

            this.$BtnContactEmailSelect = this.$Elm.getElement('button[name="select-contact-email"]');
            this.$ContactEmail.addEvent('keyup', () => {
                this.setAttribute('contactEmail', this.$ContactEmail.value);
                this.$fireChange();
            });

            this.$BtnContactEmailSelect.addEvent('click', () => {
                new ContactEmailSelectWindow({
                    autoclose: false,
                    userId   : this.getAttribute('userId'),
                    events   : {
                        onSubmit: (emailAddress, Win) => {
                            Win.close();
                            this.$ContactEmail.value = emailAddress;
                            this.setAttribute('contactEmail', emailAddress);

                            this.$fireChange();
                        }
                    }
                }).open();
            });

            // Customer address
            this.$Company = this.$Elm.getElement('[name="company"]');
            this.$Street  = this.$Elm.getElement('[name="street_no"]');
            this.$Zip     = this.$Elm.getElement('[name="zip"]');
            this.$City    = this.$Elm.getElement('[name="city"]');

            this.$Table          = this.$Elm.getElement('.quiqqer-erp-userdata--customer');
            this.$rows           = this.$Table.getElements('.closable');
            this.$AddressRow     = this.$Table.getElement('.address-row');
            this.$AddressField   = this.$Table.getElement('[name="address"]');
            this.$AddressDisplay = null;
            this.$triggerChange  = null;

            this.$AddressField.type = 'hidden';

            this.$AddressDisplay = new Element('input', {
                'class' : 'field-container-field',
                disabled: true
            }).inject(this.$AddressField, 'after');

            return this.$Elm;
        },

        /**
         * Return the data value
         *
         * @return {Object}
         */
        getValue: function () {
            const result = {};

            fields.forEach((field) => {
                if (this.getAttribute(field)) {
                    result[field] = this.getAttribute(field);
                }
            });

            return result;
        },

        /**
         * Set the complete data values
         *
         * @param {Object} data
         * @return {void}
         */
        setValue: function (data) {
            if (this.$CustomerEdit) {
                this.$CustomerEdit.setStyle('display', 'inline');
            }

            let dataPromise = Promise.resolve();

            if ('userId' in data) {
                this.$setValues = true;

                if (this.$CustomerSelect) {
                    this.$CustomerSelect.addItem(data.userId);
                }

                dataPromise = this.$setDataByUserId(data.userId);
            }

            dataPromise.then(() => {
                fields.forEach((field) => {
                    if (typeof data[field] !== 'undefined') {
                        this.setAttribute(field, data[field]);
                    }
                });

                this.$refreshValues();

                this.$setValues = false;
            });
        },

        /**
         * Refresh the displayed address values.
         *
         * @return {void}
         */
        $refreshValues: function () {
            const checkVal = function (val) {
                return !(!val || val === '' || val === 'false');
            };

            if (checkVal(this.getAttribute('company'))) {
                this.$Company.value = this.getAttribute('company');
            }

            if (checkVal(this.getAttribute('street_no'))) {
                this.$Street.value = this.getAttribute('street_no');
            }

            if (checkVal(this.getAttribute('zip'))) {
                this.$Zip.value = this.getAttribute('zip');
            }

            if (checkVal(this.getAttribute('city'))) {
                this.$City.value = this.getAttribute('city');
            }

            this.$AddressDisplay.value = this.$getAddressLabel();

            if (this.getAttribute('isCommercial')) {
                if (this.getAttribute('contactPerson') !== false) {
                    this.$ContactPerson.value = this.getAttribute('contactPerson');
                } else if (this.getAttribute('contact_person') !== false) {
                    this.$ContactPerson.value = this.getAttribute('contact_person');
                }

                this.$RowContactPerson.setStyle('display', null);
            } else {
                this.$RowContactPerson.setStyle('display', 'none');
            }

            if (this.getAttribute('contactEmail') !== false) {
                this.$ContactEmail.value = this.getAttribute('contactEmail');
            } else if (this.getAttribute('contact_email') !== false) {
                this.$ContactEmail.value = this.getAttribute('contact_email');
            }
        },

        /**
         * Get address label.
         *
         * @param {Object} [address] - Build address label based on given address; if omitted, use attributes.
         * @return {string}
         */
        $getAddressLabel(address) {
            const getVal = (key) => {
                let val;

                if (address) {
                    val = key in address ? address.key : false;
                } else {
                    val = this.getAttribute(key);
                }

                if (!val || val === '' || val === 'false') {
                    return false;
                }

                return val;
            };

            const addressLabelParts = [];

            const company = getVal('company');

            if (company) {
                addressLabelParts.push(company);
            }

            const streetNo = getVal('street_no');

            if (streetNo) {
                addressLabelParts.push(streetNo);
            }

            const zip = getVal('zip');

            if (zip) {
                addressLabelParts.push(zip);
            }

            const city = getVal('city');

            if (city) {
                addressLabelParts.push(city);
            }

            return addressLabelParts.join(', ');
        },

        /**
         * Set address data via user id.
         *
         * @param userId
         * @return {Promise}
         */
        $setDataByUserId: function (userId) {
            this.$oldUserId = this.getAttribute('userId');

            this.$clearData();

            this.setAttribute('userId', userId);

            if (this.$CustomerEdit) {
                this.$CustomerEdit.setStyle('display', 'inline');
            }

            if (!this.$Elm) {
                return Promise.resolve();
            }

            if (!userId || userId === '') {
                return Promise.resolve();
            }

            this.$ContactPerson.disabled          = true;
            this.$BtnContactPersonSelect.disabled = true;
            this.$BtnContactEmailSelect.disabled  = true;

            return this.$getUser().then((User) => {
                if (!User) {
                    return;
                }

                this.setAttribute('isCommercial', User.getAttribute('quiqqer.erp.isNettoUser'));

                return this.getAddressList(User).then((addresses) => {
                    return [User, addresses];
                });
            }).then((result) => {
                if (!result) {
                    return;
                }

                const addresses = result[1];

                if (!addresses.length) {
                    this.$AddressRow.setStyle('display', 'none');
                    return;
                }

                let defaultAddress = addresses[0];

                // Set address data
                this.$setDataByAddress(defaultAddress);
            }).then((contactEmailAddress) => {
                if (contactEmailAddress && !this.$setValues) {
                    this.$ContactEmail.value = contactEmailAddress;
                    this.setAttribute('contactEmail', contactEmailAddress);
                }
            }).then(() => {
                this.$ContactPerson.disabled          = false;
                this.$BtnContactPersonSelect.disabled = false;
                this.$BtnContactEmailSelect.disabled  = false;
            }).then(() => {
                this.$fireChange();
            });
        },

        /**
         * Sets data by specific address.
         *
         * @param {Object} address - Adress data
         * @return {void}
         */
        $setDataByAddress: function (address) {
            this.setAttributes(address);
            this.$AddressField.value = address.id;

            this.setAttribute('id', address.id);
            this.setAttribute('addressId', address.id);

            this.$refreshValues();

            this.$AddressRow.setStyle('display', null);
        },

        /**
         * Clears all fields.
         *
         * @return {void}
         */
        $clearData: function () {
            this.$Company.set('value', '');
            this.$Street.set('value', '');
            this.$Zip.set('value', '');
            this.$City.set('value', '');

            this.$AddressRow.setStyle('display', 'none');

            this.$ContactPerson.value             = '';
            this.$BtnContactPersonSelect.disabled = true;

            this.$ContactEmail.value             = '';
            this.$BtnContactEmailSelect.disabled = true;

            fields.forEach((field) => {
                this.setAttribute(field, null);
            });
        },

        /**
         * Set a address to the user data
         *
         * @param {String|Number} addressId
         * @return {Promise}
         */
        setAddressId: function (addressId) {
            var self = this;

            return new Promise(function (resolve) {
                if (self.getAttribute('userId') === '') {
                    return Promise.resolve([]);
                }

                QUIAjax.get('ajax_users_address_get', function (address) {
                    self.setAttributes(address);
                    self.$refreshValues();

                    if (!self.$setValues) {
                        self.$fireChange();
                    }

                    if (self.$CustomerEdit) {
                        self.$CustomerEdit.setStyle('display', 'inline');
                    }

                    resolve(address);
                }, {
                    uid: self.getAttribute('userId'),
                    aid: addressId
                });
            });
        },

        /**
         * Return the loaded user object.
         *
         * @returns {Promise}
         */
        $getUser: function () {
            const userId = this.getAttribute('userId');

            if (!userId || userId === '') {
                return Promise.reject();
            }

            const User = Users.get(userId);

            if (User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         * Get user address list.
         *
         * @param User
         * @return {Promise}
         */
        getAddressList: function (User) {
            return new Promise((resolve, reject) => {
                return User.getAddressList().then((result) => {
                    if (result.length) {
                        return resolve(result);
                    }

                    // create new address
                    return this.openCreateAddressDialog(User).then(function () {
                        return User.getAddressList().then(resolve);
                    }).catch(reject);
                }).catch(function () {
                    resolve([]);
                });
            });
        },

        /**
         * Open the address window
         *
         * @return {Promise}
         */
        openAddressWindow: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                new AddressWindow({
                    userId: self.getAttribute('userId'),
                    events: {
                        onSubmit: function (Win, addressId) {
                            resolve(addressId);
                            Win.close();
                        },
                        onCancel: reject
                    }
                }).open();
            });
        },

        /**
         * Events
         */

        /**
         * event on import
         */
        $onInject: function () {
            const CustomerSelectElm = this.$Elm.getElement('[name="customer"]');

            this.$Elm.getElement('button[name="select-address"]').addEvent('click', () => {
                this.openAddressWindow().then((addressId) => {
                    return this.setAddressId(addressId);
                }).catch(function () {
                    // nothing
                });
            });

            QUI.parse(this.$Elm).then(() => {
                this.$CustomerSelect = QUI.Controls.getById(
                    CustomerSelectElm.get('data-quiid')
                );

                this.$CustomerSelect.addEvents({
                    onChange: (Control) => {
                        if (this.$loading) {
                            this.$loading = false;
                            return;
                        }

                        if (this.$setValues) {
                            return;
                        }

                        this.$setDataByUserId(Control.getValue());
                    },

                    onSearch: (Control, searchTerm) => {
                        if (searchTerm) {
                            this.setAttribute('name', searchTerm);
                        } else {
                            this.setAttribute('name', false);
                        }

                        this.$fireChange();
                    },

                    onRemoveItem: () => {
                        if (this.$CustomerEdit) {
                            this.$CustomerEdit.setStyle('display', 'none');
                        }

                        this.$BtnContactPersonSelect.disabled = true;
                        this.$ContactPerson.value             = '';

                        this.$fireChange();
                    }
                });

                if (this.getAttribute('userId')) {
                    this.$CustomerSelect.addItem(this.getAttribute('userId'));
                } else {
                    this.$loading = false;

                    if (this.getAttribute('name')) {
                        this.$CustomerSelect.$Search.value = this.getAttribute('name');
                    }
                }

                if (this.getElm().getParent('.qui-panel')) {
                    this.$Panel = QUI.Controls.getById(
                        this.getElm().getParent('.qui-panel').get('data-quiid')
                    );
                }
            });
        },

        /**
         * fire the change event
         */
        $fireChange: function () {
            if (this.$triggerChange) {
                clearTimeout(this.$triggerChange);
            }

            this.$triggerChange = (function () {
                this.fireEvent('change', [this.getValue(), this]);
            }).delay(100, this);
        },

        /**
         * Address creation
         */

        /**
         *
         * @param User
         * @return {Promise}
         */
        openCreateAddressDialog: function (User) {
            return new Promise(function (resolve, reject) {
                require([
                    'package/quiqqer/erp/bin/backend/controls/userData/customers/customer/address/Window'
                ], function (Win) {
                    new Win({
                        userId: User.getId(),
                        events: {
                            onSubmit: resolve,
                            onCancel: function () {
                                reject('No User selected');
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * Extras
         */

        /**
         * Toggle the extra view
         *
         * @returns {Promise}
         */
        toggleExtras: function () {
            if (this.$extrasAreOpen) {
                return this.closeExtras();
            }

            return this.openExtras();
        },

        /**
         * Open the extra data
         *
         * @return {Promise}
         */
        openExtras: function () {
            var self = this;

            return new Promise(function (resolve) {
                self.$rows.setStyles({
                    height  : 0,
                    opacity : 0,
                    overflow: 'hidden',
                    position: 'relative'
                });

                self.$rows.setStyle('display', 'block');

                var height = self.$rows[0].getScrollSize().y;

                moofx(self.$rows).animate({
                    height: height
                }, {
                    duration: 250,
                    callback: function () {
                        self.$rows.setStyles({
                            display : null,
                            height  : null,
                            overflow: null,
                            position: null
                        });

                        moofx(self.$rows).animate({
                            opacity: 1
                        }, {
                            duration: 250,
                            callback: function () {
                                self.$Extras.set({
                                    html: '<span class="fa fa-chevron-up"></span> ' +
                                        QUILocale.get(pkg, 'UserData.btn.extraClose')
                                });

                                self.$extrasAreOpen = true;
                                resolve();
                            }
                        });
                    }
                });
            });
        },

        /**
         * Close the extra data
         *
         * @return {Promise}
         */
        closeExtras: function () {
            var self = this;

            return new Promise(function (resolve) {
                moofx(self.$rows).animate({
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        self.$rows.setStyle('display', 'none');
                        self.$extrasAreOpen = false;

                        self.$Extras.set({
                            html: '<span class="fa fa-chevron-right"></span> ' +
                                QUILocale.get(pkg, 'UserData.btn.extra')
                        });

                        resolve();
                    }
                });
            });
        },

        /**
         * open the user edit panel for the customer
         */
        editCustomer: function () {
            var self = this;

            if (this.$Panel) {
                this.$Panel.Loader.show();
            }

            require(['package/quiqqer/customer/bin/backend/Handler'], function (CustomerHandler) {
                CustomerHandler.openCustomer(self.getAttribute('userId')).then(function () {
                    self.$Panel.Loader.hide();
                });
            });
        },

        /**
         * Set the contact person by an address data object to the contact person input field.
         *
         * @param {Object} address - Address data
         * @return {void}
         */
        $setContactPersonByAddress: function (address) {
            if (typeof address.salutation === 'undefined' &&
                typeof address.firstname === 'undefined' &&
                typeof address.lastname === 'undefined') {
                return;
            }

            const parts = [];

            if (typeof address.salutation !== 'undefined') {
                parts.push(address.salutation);
            }

            if (typeof address.firstname !== 'undefined') {
                parts.push(address.firstname);
            }

            if (typeof address.lastname !== 'undefined') {
                parts.push(address.lastname);
            }

            this.$ContactPerson.value = parts.join(' ').trim();
            this.setAttribute('contactPerson', this.$ContactPerson.value);
        },

        /**
         * @param {Number} userId
         * @return {Promise<String>}
         */
        $getContactEmailAddress: function (userId) {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_userData_getContactEmailAddress', resolve, {
                    'package': pkg,
                    userId   : userId,
                    onError  : reject
                });
            });
        }
    });
});
