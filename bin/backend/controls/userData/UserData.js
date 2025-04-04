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
    'use strict';

    const pkg = 'quiqqer/erp';

    const fields = [
        'userId',
        'addressId',
        'contactPerson',
        'contactEmail',
        'quiqqer.erp.standard.payment',
        'quiqqer.erp.customer.payment.term',

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
        Type: 'package/quiqqer/erp/bin/backend/controls/userData/UserData',

        Binds: [
            'toggleExtras',
            'editCustomer',
            '$setDataByUserId',
            '$onInject',
            '$fireChange',
            'setValue'
        ],

        options: {
            userId: false,
            addressId: false,

            editContactPerson: true,
            editContactEmail: true,

            name: '',
            contactPerson: '',
            contactEmail: '',
            company: false,
            street_no: false,
            zip: false,
            city: false,

            labelUser: QUILocale.get(pkg, 'UserData.tpl.labelCustomer'),
            userSelectWindowControl: 'package/quiqqer/customer/bin/backend/controls/customer/Select',
            userPanelControl: 'package/quiqqer/customer/bin/backend/Handler'
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$CustomerSelect = null;
            this.$ContactPerson = null;
            this.$BtnContactPersonSelect = null;

            this.$ContactEmail = null;
            this.$BtnContactEmailSelect = null;
            this.$RowContactPerson = null;

            this.$Extras = null;
            this.$Company = null;
            this.$Street = null;
            this.$Zip = null;
            this.$City = null;
            this.$Table = null;
            this.$AddressRow = null;
            this.$AddressField = null;

            this.$rows = [];
            this.$extrasAreOpen = false;
            this.$oldUserId = false;

            this.$loading = true;
            this.$setValues = false;

            this.$Panel = null;
        },

        /**
         * Create the DOMNoe Element
         * @returns {*}
         */
        create: function () {
            const labelUser = this.getAttribute('labelUser');

            function ignoreAutoFill(node) {
                node.role = 'presentation';
                node.autocomplete = 'off';
            }

            this.$Elm = new Element('div', {
                html: Mustache.render(template, {
                    labelTitle: labelUser,
                    labelCustomer: labelUser,
                    labelAddress: QUILocale.get('quiqqer/core', 'address'),
                    labelCompany: QUILocale.get('quiqqer/core', 'company'),
                    labelStreet: QUILocale.get('quiqqer/core', 'street'),
                    labelZip: QUILocale.get('quiqqer/core', 'zip'),
                    labelCity: QUILocale.get('quiqqer/core', 'city'),
                    labelExtra: QUILocale.get(pkg, 'UserData.btn.extra'),
                    labelUserEdit: QUILocale.get(pkg, 'UserData.btn.userEdit', {labelUser}),
                    labelContactPerson: QUILocale.get(pkg, 'UserData.tpl.labelContactPerson'),
                    labelContactEmail: QUILocale.get(pkg, 'UserData.tpl.labelContactEmail'),
                    placeholderContactPerson: QUILocale.get(pkg, 'UserData.tpl.placeholderContactPerson'),
                    placeholderContactEmail: QUILocale.get(pkg, 'UserData.tpl.placeholderContactEmail'),

                    userSelectWindowControl: this.getAttribute('userSelectWindowControl')
                })
            });

            // Extras (show address fields)
            this.$Extras = this.$Elm.getElement('.quiqqer-erp-userdata-address-opener');
            this.$Extras.addEvent('click', this.toggleExtras);

            // Customer edit (button that opens customer / user panel)
            this.$CustomerEdit = this.$Elm.getElement('.quiqqer-erp-userdata-address-userEdit');
            this.$CustomerEdit.addEvent('click', this.editCustomer);

            // Contact person
            this.$RowContactPerson = this.$Elm.getElement('.quiqqer-erp-userdata-row-contactPerson');
            this.$ContactPerson = this.$Elm.getElement('input[name="contact_person"]');
            this.$BtnContactPersonSelect = this.$Elm.getElement('button[name="select-contact-id-address"]');

            this.$ContactPerson.addEvent('keyup', () => {
                this.setAttribute('contactPerson', this.$ContactPerson.value);
            });

            this.$BtnContactPersonSelect.addEvent('click', () => {
                new AddressWindow({
                    autoclose: false,
                    userId: this.getAttribute('userId'),
                    events: {
                        onSubmit: (Win, addressId, address) => {
                            Win.close();

                            this.$setContactPersonByAddress(address);
                            this.$fireChange();
                        }
                    }
                }).open();
            });

            // Contact email
            this.$RowContactEmail = this.$Elm.getElement('.quiqqer-erp-userdata-row-contactEmail');
            this.$RowContactEmail.setStyle('display', 'none');

            this.$ContactEmail = this.$Elm.getElement('input[name="contact_email"]');

            this.$BtnContactEmailSelect = this.$Elm.getElement('button[name="select-contact-email"]');
            this.$ContactEmail.addEvent('keyup', () => {
                this.setAttribute('contactEmail', this.$ContactEmail.value);
            });

            this.$BtnContactEmailSelect.addEvent('click', () => {
                new ContactEmailSelectWindow({
                    autoclose: false,
                    userId: this.getAttribute('userId'),
                    events: {
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
            this.$Street = this.$Elm.getElement('[name="street_no"]');
            this.$Zip = this.$Elm.getElement('[name="zip"]');
            this.$City = this.$Elm.getElement('[name="city"]');

            ignoreAutoFill(this.$Company);
            ignoreAutoFill(this.$Street);
            ignoreAutoFill(this.$Zip);
            ignoreAutoFill(this.$City);

            this.$Table = this.$Elm.getElement('.quiqqer-erp-userdata--customer');
            this.$rows = this.$Table.getElements('.closable');
            this.$AddressRow = this.$Table.getElement('.address-row');
            this.$AddressField = this.$Table.getElement('[name="address"]');
            this.$AddressDisplay = null;
            this.$triggerChange = null;

            this.$AddressField.type = 'hidden';

            this.$AddressDisplay = new Element('input', {
                'class': 'field-container-field',
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
                } else {
                    result[field] = false;
                }
            });

            return result;
        },

        getAddress: function () {
            return {
                id: this.getAttribute('addressId'),
                contactEmail: this.getAttribute('contactEmail'),
                salutation: this.getAttribute('salutation'),
                firstname: this.getAttribute('firstname'),
                lastname: this.getAttribute('lastname'),
                city: this.getAttribute('city'),
                zip: this.getAttribute('zip'),
                company: this.getAttribute('company'),
                street_no: this.getAttribute('street_no'),
                country: this.getAttribute('country')
            };
        },

        /**
         * Set the complete data values
         *
         * @param {Object} data
         * @return {Promise}
         */
        setValue: function (data) {
            if (this.$CustomerEdit) {
                this.$CustomerEdit.setStyle('display', 'inline');
            }

            let dataPromise = Promise.resolve();
            let addressPromise = Promise.resolve();

            if ('userId' in data && data.userId) {
                if (this.$CustomerSelect) {
                    this.$setValues = true;
                    this.$CustomerSelect.addItem(data.userId);

                    (() => {
                        this.$setValues = false;
                    }).delay(200);
                }

                dataPromise = this.$setDataByUserId(data.userId);
            }

            if ('addressId' in data && data.addressId) {
                addressPromise = this.setAddressId(data.addressId);
            }

            return dataPromise.then(addressPromise).then(() => {
                fields.forEach((field) => {
                    if (typeof data[field] !== 'undefined') {
                        this.setAttribute(field, data[field]);
                    }
                });

                this.$refreshValues();
            });
        },

        refresh: function () {
            this.$refreshValues();
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
                } else {
                    if (this.getAttribute('contact_person') !== false) {
                        this.$ContactPerson.value = this.getAttribute('contact_person');
                    }
                }

                this.$RowContactPerson.setStyle('display', null);
            } else {
                this.$RowContactPerson.setStyle('display', 'none');
            }

            if (this.getAttribute('contactEmail') !== false) {
                this.$ContactEmail.value = this.getAttribute('contactEmail');
            } else {
                if (this.getAttribute('contact_email') !== false) {
                    this.$ContactEmail.value = this.getAttribute('contact_email');
                    this.setAttribute('contactEmail', this.getAttribute('contact_email'));
                }
            }

            if (!this.getAttribute('userId')) {
                this.$RowContactEmail.setStyle('display', 'none');
            } else {
                this.$RowContactEmail.setStyle('display', null);
            }
        },

        /**
         * Get address label.
         *
         * @param {Object} [address] - Build address label based on given address; if omitted, use attributes.
         * @return {string}
         */
        $getAddressLabel: function (address) {
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

            //this.$clearData();
            this.setAttribute('userId', userId);
            this.setAttribute('userUuid', userId);

            if (this.$CustomerEdit) {
                this.$CustomerEdit.setStyle('display', 'inline');
            }

            if (!this.$Elm) {
                return Promise.resolve();
            }

            if (!userId || userId === '') {
                return Promise.resolve();
            }

            this.$ContactPerson.disabled = true;
            this.$BtnContactPersonSelect.disabled = true;
            this.$BtnContactEmailSelect.disabled = true;

            let contactPersonAddress = null;
            let erpAddress = null;

            return this.$getUser().then((User) => {
                if (!User) {
                    return;
                }

                this.setAttribute('isCommercial', 0);

                if (parseInt(User.getAttribute('quiqqer.erp.isNettoUser')) === 1 || this.getAttribute('company')) {
                    this.setAttribute('isCommercial', 1);
                }

                contactPersonAddress = User.getAttribute('quiqqer.erp.customer.contact.person');
                erpAddress = User.getAttribute('quiqqer.erp.address');

                this.setAttribute(
                    'quiqqer.erp.standard.payment',
                    User.getAttribute('quiqqer.erp.standard.payment')
                );

                this.setAttribute(
                    'quiqqer.erp.customer.payment.term',
                    User.getAttribute('quiqqer.erp.customer.payment.term')
                );

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

                let mail;
                let currentAddress = false;
                let defaultAddress = false;

                if (erpAddress) {
                    currentAddress = erpAddress;
                }

                for (let i = 0; i < addresses.length; i++) {
                    if (addresses[i].default) {
                        defaultAddress = addresses[i];

                        if (!currentAddress) {
                            currentAddress = addresses[i];
                        }
                        break;
                    }
                }

                if (!currentAddress) {
                    currentAddress = addresses[0];
                }

                if (typeof currentAddress.mail !== 'undefined' && !this.$setValues) {
                    try {
                        mail = JSON.decode(currentAddress.mail);

                        if (typeof mail === 'string' && mail !== '') {
                            this.$ContactEmail.value = mail;
                            this.setAttribute('contactEmail', mail);
                        }

                        if (Array.isArray(mail) && typeof mail[0] !== 'undefined') {
                            this.$ContactEmail.value = mail[0];
                            this.setAttribute('contactEmail', mail[0]);
                        }
                    } catch (e) {
                    }
                }

                if (this.$ContactEmail.value === '') {
                    try {
                        mail = JSON.decode(defaultAddress.mail);

                        if (typeof mail === 'string' && mail !== '') {
                            this.$ContactEmail.value = mail;
                            this.setAttribute('contactEmail', mail);
                        }

                        if (Array.isArray(mail) && typeof mail[0] !== 'undefined') {
                            this.$ContactEmail.value = mail[0];
                            this.setAttribute('contactEmail', mail[0]);
                        }
                    } catch (e) {
                    }
                }

                // Set address data
                this.$setDataByAddress(currentAddress);
            }).then(() => {
                this.$ContactPerson.disabled = false;
                this.$BtnContactPersonSelect.disabled = false;
                this.$BtnContactEmailSelect.disabled = false;

                return new Promise((resolve) => {
                    if (!contactPersonAddress) {
                        return resolve();
                    }

                    QUIAjax.get('ajax_users_address_get', (address) => {
                        this.$setContactPersonByAddress(address);
                        resolve();
                    }, {
                        uid: this.getAttribute('userId'),
                        aid: contactPersonAddress
                    });
                });
            }).then(() => {
                this.$fireChange();
            }).catch((err) => {
                console.error(err);
            });
        },

        /**
         * Sets data by specific address.
         *
         * @param {Object} address - Address data
         * @return {void}
         */
        $setDataByAddress: function (address) {
            this.setAttributes(address);
            this.$AddressField.value = address.id;

            this.setAttribute('id', address.id);
            this.setAttribute('addressId', address.uuid);

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

            this.$ContactPerson.value = '';
            this.$BtnContactPersonSelect.disabled = true;

            this.$ContactEmail.value = '';
            this.$BtnContactEmailSelect.disabled = true;

            fields.forEach((field) => {
                this.setAttribute(field, null);
            });
        },

        /**
         * Set an address to the user data
         *
         * @param {String|Number} addressId
         * @return {Promise}
         */
        setAddressId: function (addressId) {
            const self = this;

            return new Promise(function (resolve) {
                if (self.getAttribute('userId') === '') {
                    return Promise.resolve([]);
                }

                QUIAjax.get('ajax_users_address_get', function (address) {
                    if (!('id' in address)) {
                        address.id = addressId;
                    }

                    self.$setDataByAddress(address);
                    self.$fireChange();
                    resolve();
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
                if (!User.getId()) {
                    return resolve([]);
                }

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
            const self = this;

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
                            this.$setValues = false;
                            return;
                        }

                        this.setAttribute('contactPerson', '');
                        this.setAttribute('contactEmail', '');

                        this.$ContactPerson.value = '';
                        this.$ContactEmail.value = '';

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

                        this.$RowContactPerson.setStyle('display', 'none');
                        this.$RowContactEmail.setStyle('display', 'none');

                        this.$BtnContactPersonSelect.disabled = true;
                        this.$ContactPerson.value = '';

                        this.$fireChange();
                    }
                });

                if (this.getAttribute('userId') && parseInt(this.getAttribute('userId')) !== 0) {
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
            const self = this;

            return new Promise(function (resolve) {
                self.$rows.setStyles({
                    height: 0,
                    opacity: 0,
                    overflow: 'hidden',
                    position: 'relative'
                });

                self.$rows.setStyle('display', 'block');

                const height = self.$rows[0].getScrollSize().y;

                moofx(self.$rows).animate({
                    height: height
                }, {
                    duration: 250,
                    callback: function () {
                        self.$rows.setStyles({
                            display: null,
                            height: null,
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
            const self = this;

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
            const self = this;

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

            // E-Mail address
            const emailAddresses = JSON.decode(address.mail);

            if (emailAddresses.length) {
                const contactEmail = emailAddresses[0].trim();

                if (contactEmail !== '') {
                    this.$ContactEmail.value = contactEmail;
                    this.setAttribute('contactEmail', contactEmail);
                }
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
                    userId: userId,
                    onError: reject
                });
            });
        }
    });
});
