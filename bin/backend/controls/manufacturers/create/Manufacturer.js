/**
 * @module package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/countries/bin/Countries',
    'Locale',
    'Ajax',
    'Mustache',

    'package/quiqqer/erp/bin/backend/Manufacturers',

    'text!package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer.html',
    'css!package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer.css'

], function (QUI, QUIControl, Countries, QUILocale, QUIAjax, Mustache, Manufacturers, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer',

        Binds: [
            '$onInject',
            'next',
            'previous'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Container = null;
            this.$List      = null;
            this.$Form      = null;
            this.$GroupList = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * event: on create
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-erp-manufacturers-create');
            this.$Elm.set('data-qui', 'package/quiqqer/erp/bin/backend/controls/manufacturers/create/Manufacturer');

            var lgPrefix = 'controls.manufacturers.create.Manufacturer.tpl.';

            this.$Elm.set('html', Mustache.render(template, {
                manufacturerNoHeader     : QUILocale.get(lg, lgPrefix + 'manufacturerNoHeader'),
                manufacturerNoText       : QUILocale.get(lg, lgPrefix + 'manufacturerNoText'),
                manufacturerNoInputHeader: QUILocale.get(lg, lgPrefix + 'manufacturerNoInputHeader'),
                manufacturerDataHeader   : QUILocale.get(lg, lgPrefix + 'manufacturerDataHeader'),
                manufacturerDataText     : QUILocale.get(lg, lgPrefix + 'manufacturerDataText'),
                manufacturerGroupsHeader : QUILocale.get(lg, lgPrefix + 'manufacturerGroupsHeader'),
                manufacturerGroupsText   : QUILocale.get(lg, lgPrefix + 'manufacturerGroupsText'),

                textAddressCompany   : QUILocale.get('quiqqer/quiqqer', 'company'),
                textAddressSalutation: QUILocale.get('quiqqer/quiqqer', 'salutation'),
                textAddressFirstname : QUILocale.get('quiqqer/quiqqer', 'firstname'),
                textAddressLastname  : QUILocale.get('quiqqer/quiqqer', 'lastname'),
                textAddressStreet    : QUILocale.get('quiqqer/quiqqer', 'street'),
                textAddressZIP       : QUILocale.get('quiqqer/quiqqer', 'zip'),
                textAddressCity      : QUILocale.get('quiqqer/quiqqer', 'city'),
                textAddressCountry   : QUILocale.get('quiqqer/quiqqer', 'country'),

                textGroup : QUILocale.get(lg, lgPrefix + 'textGroup'),
                textGroups: QUILocale.get(lg, lgPrefix + 'textGroups'),

                previousButton: QUILocale.get(lg, lgPrefix + 'previousButton'),
                nextButton    : QUILocale.get(lg, lgPrefix + 'nextButton')
            }));

            this.$Form      = this.$Elm.getElement('form');
            this.$GroupList = this.$Elm.getElement('.quiqqer-erp-manufacturers-create-manufacturerGroups-list');

            // key events
            var self           = this;
            var ManufacturerId = this.$Elm.getElement('[name="manufacturerId"]');
            var Company        = this.$Elm.getElement('[name="address-company"]');
            var Country        = this.$Elm.getElement('[name="address-country"]');

            ManufacturerId.addEvent('keydown', function (event) {
                if (event.key === 'tab') {
                    event.stop();
                    self.next().then(function () {
                        Company.focus();
                    });
                }
            });

            ManufacturerId.addEvent('keyup', function (event) {
                if (event.key === 'enter') {
                    event.stop();
                    self.next();
                }
            });

            Company.addEvent('keydown', function (event) {
                if (event.key === 'tab' && event.shift) {
                    event.stop();
                    self.previous().then(function () {
                        ManufacturerId.focus();
                    });
                }
            });

            Country.addEvent('keydown', function (event) {
                if (event.key === 'tab') {
                    event.stop();
                    self.next();
                }
            });

            this.$Container = this.$Elm.getElement('.quiqqer-erp-manufacturers-create-container');
            this.$List      = this.$Elm.getElement('.quiqqer-erp-manufacturers-create-container ul');
            this.$Next      = this.$Elm.getElement('[name="next"]');
            this.$Previous  = this.$Elm.getElement('[name="previous"]');

            this.$Next.addEvent('click', this.next);
            this.$Previous.addEvent('click', this.previous);

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            Promise.all([
                Manufacturers.getManufacturerGroups(),
                Countries.getCountries()
            ]).then(function (result) {
                // Group list
                var groups = result[0];

                for (var i = 0, len = groups.length; i < len; i++) {
                    var Group = groups[i];

                    new Element('li', {
                        html: '<label>' +
                            '<input type="checkbox" data-id="' + Group.id + '"/>' +
                            '<span>' + Group.name + '</span>' +
                            '</label>'
                    }).inject(self.$GroupList);
                }

                // Country list
                var countries     = result[1];
                var CountrySelect = self.$Elm.getElement('[name="address-country"]');

                for (var code in countries) {
                    if (!countries.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html : countries[code]
                    }).inject(CountrySelect);
                }
            }).then(function () {
                return QUI.parse(self.$Elm);
            }).then(function () {
                self.showManufacturerNumber();
            });
        },

        /**
         * Create the manufacturer
         */
        createManufacturer: function () {
            var self           = this;
            var elements       = this.$Form.elements;
            var manufacturerId = elements.manufacturerId.value;
            var groupIds       = [];

            var groupInputs = this.$GroupList.getElement('input');

            groupInputs.forEach(function (Input) {
                if (Input.checked) {
                    groupIds.push(Input.get('data-id'));
                }
            });

            var Address = {
                'salutation': elements['address-salutation'].value,
                'firstname' : elements['address-firstname'].value,
                'lastname'  : elements['address-lastname'].value,
                'company'   : elements['address-company'].value,
                'street_no' : elements['address-street_no'].value,
                'zip'       : elements['address-zip'].value,
                'city'      : elements['address-city'].value,
                'country'   : elements['address-country'].value
            };

            this.fireEvent('createManufacturerBegin', [this]);

            QUIAjax.post('package_quiqqer_manufacturer_ajax_backend_create_createManufacturer', function (manufacturerId) {
                self.fireEvent('createManufacturerEnd', [self, manufacturerId]);
            }, {
                'package'     : 'quiqqer/erp',
                manufacturerId: manufacturerId,
                address       : JSON.encode(Address),
                groupIds      : JSON.encode(groupIds)
            }, function (e) {
                console.log("TODO: hide loader");
            });
        },

        /**
         * Show next step
         */
        next: function () {
            if (this.$Next.get('data-last')) {
                return this.createManufacturer();
            }

            var self  = this;
            var steps = this.$List.getElements('li');
            var pos   = this.$List.getPosition(this.$Container);
            var top   = pos.y;

            var height       = this.$Container.getSize().y;
            var scrollHeight = this.$Container.getScrollSize().y;
            var newTop       = this.$roundToStepPos(top - height);

            // change last step button
            if (newTop - height <= scrollHeight * -1) {
                this.$Next.set('html', QUILocale.get(lg, 'window.manufacturer.creation.create'));
                this.$Next.set('data-last', 1);
            }

            // check if last step
            if (newTop <= steps.length * height * -1) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                moofx(self.$List).animate({
                    top: newTop
                }, {
                    callback: resolve
                });
            });
        },

        /**
         * Previous next step
         */
        previous: function () {
            var self = this;
            var pos  = this.$List.getPosition(this.$Container);
            var top  = pos.y;

            var height = this.$Container.getSize().y;
            var newTop = this.$roundToStepPos(top + height);

            this.$Next.set('html', QUILocale.get(lg, 'window.manufacturer.creation.next'));
            this.$Next.set('data-last', null);

            if (newTop > 0) {
                newTop = 0;
            }

            return new Promise(function (resolve) {
                moofx(self.$List).animate({
                    top: newTop
                }, {
                    callback: resolve
                });
            });
        },

        /**
         *
         * @param currentPos
         * @return {number}
         */
        $roundToStepPos: function (currentPos) {
            var height = this.$Container.getSize().y;
            var pos    = Math.round(currentPos / height) * -1;

            return pos * height * -1;
        },

        /**
         * Show the manufacturer number step
         */
        showManufacturerNumber: function () {
            var self = this;

            this.$getNextManufacturerNo().then(function (manufacturerNo) {
                var Input = self.$Elm.getElement('input');

                Input.value = manufacturerNo;
                Input.focus();

                self.fireEvent('load', [self]);
            });
        },

        /**
         * Get next available QUIQQER user id
         *
         * @return {Promise}
         */
        $getNextManufacturerNo: function () {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_manufacturers_create_getNextId', resolve, {
                    'package': 'quiqqer/erp'
                });
            });
        }
    });
});
