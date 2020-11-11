/**
 * @module package/quiqqer/erp/bin/backend/controls/manufacturers/Administration
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event manufacturerOpenBegin [self, userId]
 * @event onManufacturerOpen [self, userId, Panel]
 * @event onManufacturerOpenEnd [self, userId, Panel]
 * @event onListOpen [self]
 */
define('package/quiqqer/erp/bin/backend/controls/manufacturers/Administration', [

    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'utils/Panels',

    'package/quiqqer/erp/bin/backend/Manufacturers',

    'controls/grid/Grid',
    'Mustache',
    'Locale',
    'Ajax',
    'Users',

    'text!package/quiqqer/erp/bin/backend/controls/manufacturers/Administration.html',
    'css!package/quiqqer/erp/bin/backend/controls/manufacturers/Administration.css'

], function (QUIControl, QUIConfirm, QUIPanelUtils, Manufacturers, Grid, Mustache, QUILocale, QUIAjax, Users,
             template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/manufacturers/Administration',

        Binds: [
            '$onInject',
            '$onDestroy',
            '$onUserRefresh',
            '$onUserChange',
            '$editComplete',
            '$gridDblClick',
            '$gridClick',
            'refresh',
            'toggleFilter',
            'openDeleteWindow',
            'openAddWindow'
        ],

        options: {
            page          : 1,
            perPage       : 50,
            editable      : true,
            submittable   : false,
            add           : true,
            manufacturerId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$SearchContainer      = null;
            this.$SearchInput          = null;
            this.$FilterButton         = null;
            this.$manufacturerGroupIds = [];

            this.$ManufacturerPanel = null;
            this.$GroupSwitch       = null;
            this.$GridContainer     = null;
            this.$Grid              = null;

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            Users.addEvents({
                onSwitchStatus: this.$onUserChange,
                onDelete      : this.$onUserChange,
                onRefresh     : this.$onUserRefresh,
                onSave        : this.$onUserRefresh
            });
        },

        /**
         * Create the DOMNode element
         */
        create: function () {
            var self = this;

            this.$Elm = new Element('div', {
                'class': 'quiqqer-erp-manufacturers-administration',
                html   : Mustache.render(template, {
                    searchPlaceholder: QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.searchPlaceholder'),
                    filterTitle      : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterTitle'),
                    filterUserId     : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterUserId'),
                    filterUsername   : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterUsername'),
                    filterFirstname  : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterFirstname'),
                    filterLastname   : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterLastname'),
                    filterEmail      : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterEmail'),
                    filterCompany    : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterCompany'),
                    dateFilterTitle  : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.dateFilterTitle'),
                    dateFilterFrom   : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.dateFilterFrom'),
                    dateFilterTo     : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.dateFilterTo')
                })
            });

            this.$SearchContainer = this.$Elm.getElement('.quiqqer-erp-manufacturers-administration-search');
            this.$GridContainer   = this.$Elm.getElement('.quiqqer-erp-manufacturers-administration-grid');
            this.$SearchInput     = this.$Elm.getElement('[name="search"]');
            this.$SubmitButton    = this.$Elm.getElement('[name="submit"]');
            this.$FilterButton    = this.$Elm.getElement('button[name="filter"]');

            this.$FilterButton.addEvent('click', function (event) {
                event.stop();
                self.toggleFilter();
            });

            this.$SearchContainer.getElement('form').addEvent('submit', function (event) {
                event.stop();
            });

            this.$SubmitButton.addEvent('click', function () {
                self.refresh();
            });

            this.$SearchInput.addEvent('keydown', function (event) {
                if (event.key === 'enter') {
                    event.stop();
                }
            });

            this.$SearchInput.addEvent('keyup', function (event) {
                if (event.key === 'enter') {
                    self.refresh();
                }
            });

            if (!this.getAttribute('add')) {
                this.$AddButton.setStyle('display', 'none');
            }

            // create grid
            this.$Container = new Element('div');
            this.$Container.inject(this.$GridContainer);

            var columnModel = [];

            if (this.getAttribute('submittable')) {
                columnModel.push({
                    header   : '&nbsp',
                    dataIndex: 'submit_button',
                    dataType : 'node',
                    width    : 60
                });
            }

            columnModel.push({
                header   : QUILocale.get(lg, 'controls.manufacturers.Administration.tbl.header.active_status'),
                dataIndex: 'active_status',
                dataType : 'node',
                width    : 40
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'id'),
                dataIndex: 'id',
                dataType : 'integer',
                width    : 100
            });

            /*{
                header   : QUILocale.get('quiqqer/quiqqer', 'username'),
                dataIndex: 'username',
                dataType : 'integer',
                width    : 150,
                editable : editable,
                className: editable ? 'clickable' : ''
            }*/

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'company'),
                dataIndex: 'company',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'firstname'),
                dataIndex: 'firstname',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'lastname'),
                dataIndex: 'lastname',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'email'),
                dataIndex: 'email',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'group'),
                dataIndex: 'usergroup_display',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                dataIndex: 'usergroup',
                dataType : 'string',
                hidden   : true
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/quiqqer', 'c_date'),
                dataIndex: 'regdate',
                dataType : 'date',
                width    : 150
            });

            this.$Grid = new Grid(this.$Container, {
                buttons: [{
                    name     : 'add',
                    textimage: 'fa fa-plus',
                    text     : QUILocale.get(lg, 'controls.manufacturers.Administration.btn.create'),
                    events   : {
                        onClick: self.openAddWindow
                    }
                }/*, {
                    name     : 'delete',
                    textimage: 'fa fa-trash',
                    text     : QUILocale.get(lg, 'manufacturer.window.delete.title'),
                    disabled : true,
                    styles   : {
                        'float': 'right'
                    },
                    events   : {
                        onClick: self.openDeleteWindow
                    }
                }*/],

                columnModel      : columnModel,
                pagination       : true,
                filterInput      : true,
                perPage          : this.getAttribute('perPage'),
                page             : this.getAttribute('page'),
                sortOn           : this.getAttribute('field'),
                serverSort       : true,
                showHeader       : true,
                sortHeader       : true,
                alternaterows    : true,
                resizeColumns    : true,
                selectable       : true,
                multipleSelection: true,
                resizeHeaderOnly : true
            });

            // Events
            this.$Grid.addEvents({
                onClick   : this.$gridClick,
                onDblClick: this.$gridDblClick,
                // onBlur    : this.$gridBlur,
                refresh   : this.refresh
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.$Grid.disable();

            Manufacturers.getManufacturerGroupIds().then(function (manufacturerGroupIds) {
                self.$manufacturerGroupIds = manufacturerGroupIds;

                // Show info if no manufacturer groups have been defined yet
                if (!self.$manufacturerGroupIds.length) {
                    new QUIConfirm({
                        maxHeight: 300,
                        autoclose: true,

                        information: QUILocale.get(lg, 'controls.manufacturers.Administration.no_group.information'),
                        title      : QUILocale.get(lg, 'controls.manufacturers.Administration.no_group.title'),
                        texticon   : 'fa fa-exclamation-triangle',
                        text       : QUILocale.get(lg, 'controls.manufacturers.Administration.no_group.text'),
                        icon       : 'fa fa-exclamation-triangle',

                        cancel_button: false,
                        ok_button    : {
                            text     : QUILocale.get(lg, 'controls.manufacturers.Administration.no_group.submit'),
                            textimage: 'icon-ok fa fa-check'
                        }
                    }).open();

                    return;
                }

                self.refresh().then(function () {
                    self.$Grid.enable();
                });
            });
        },

        /**
         * Triggers if user double clicks a grid row
         */
        $gridDblClick: function () {
            var userId = this.$Grid.getSelectedData()[0].id;
            this.$openManufacturer(userId);
        },

        /**
         * Is the administration in a qui window?
         *
         * @return {boolean}
         */
        isInWindow: function () {
            return !!this.getElm().getParent('.qui-window-popup');
        },

        /**
         * event: on user change
         */
        $onUserRefresh: function () {
            this.refresh();
        },

        /**
         * event: on user status change
         *
         * @param Users
         * @param ids
         */
        $onUserChange: function (Users, ids) {
            var i, len;
            var data = this.$Grid.getData();

            if (typeOf(ids) === 'array') {
                var tmp = {};

                for (i = 0, len = ids.length; i < len; i++) {
                    tmp[ids[i]] = true;
                }

                ids = tmp;
            }

            for (i = 0, len = data.length; i < len; i++) {
                if (typeof ids[data[i].id] === 'undefined') {
                    continue;
                }

                // use is in list, refresh
                this.refresh();
                break;
            }
        },

        /**
         * event: on control destroy
         */
        $onDestroy: function () {
            Users.removeEvents({
                onSwitchStatus: this.$onUserChange,
                onDelete      : this.$onUserChange,
                onRefresh     : this.$onUserRefresh,
                onSave        : this.$onUserRefresh
            });
        },

        /**
         * return all selected manufacturer ids
         *
         * @return {Array}
         */
        getSelectedManufacturerIds: function () {
            if (this.$ManufacturerPanel) {
                return [this.$ManufacturerPanel.getAttribute('userId')];
            }

            return this.$Grid.getSelectedData().map(function (entry) {
                return parseInt(entry.id);
            });
        },

        /**
         * return all selected manufacturer
         *
         * @return {Array}
         */
        getSelectedManufacturer: function () {
            return this.$Grid.getSelectedData();
        },

        /**
         * Resize the grid
         *
         * @return {Promise|Promise|Promise|Promise|*}
         */
        resize: function () {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            var size = this.$GridContainer.getSize();

            return Promise.all([
                this.$Grid.setHeight(size.y - 40),
                this.$Grid.setWidth(size.x - 40)
            ]);
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            var self    = this,
                options = this.$Grid.options,
                Form    = this.$SearchContainer.getElement('form');

            var sortOn = options.sortOn;

            switch (options.sortOn) {
                case 'active_status':
                    sortOn = 'active';
                    break;

                case 'usergroup_display':
                    sortOn = 'usergroup';
                    break;
            }

            var params = {
                perPage: options.perPage || 50,
                page   : options.page || 1,
                sortBy : options.sortBy,
                sortOn : sortOn,
                search : this.$SearchInput.value,
                filter : {
                    id          : Form.elements.userId.checked ? 1 : 0,
                    username    : Form.elements.username.checked ? 1 : 0,
                    firstname   : Form.elements.firstname.checked ? 1 : 0,
                    lastname    : Form.elements.lastname.checked ? 1 : 0,
                    email       : Form.elements.email.checked ? 1 : 0,
                    company     : Form.elements.company.checked ? 1 : 0,
                    regdate_from: Form.elements['registration-from'].value,
                    regdate_to  : Form.elements['registration-to'].value
                }
            };

            this.fireEvent('refreshBegin', [this]);

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_manufacturers_search', function (result) {
                    for (var i = 0, len = result.data.length; i < len; i++) {
                        // Active status
                        var activeIcon;

                        if (result.data[i].status) {
                            activeIcon = 'fa fa-check';
                        } else {
                            activeIcon = 'fa fa-remove';
                        }

                        result.data[i].active_status = new Element('span', {
                            'class': activeIcon
                        });
                    }

                    self.$Grid.setData(result);
                    self.fireEvent('refreshEnd', [self]);
                    resolve();
                }, {
                    package: 'quiqqer/erp',
                    params : JSON.encode(params)
                });
            });
        },

        /**
         * Set user active status
         *
         * @param userId
         * @param status
         * @return {Promise}
         */
        $setStatus: function (userId, status) {
            var self = this;

            this.$Grid.disable();

            return new Promise(function (resolve, reject) {
                QUIAjax.post('ajax_users_save', function () {
                    self.$Grid.enable();
                    resolve();
                }, {
                    uid       : userId,
                    attributes: JSON.encode({
                        active: status
                    }),
                    onError   : function (err) {
                        self.$Grid.enable();
                        reject(err);
                    }
                });
            });
        },

        /**
         * Opens the user panel for a manufacturer user
         *
         * @param {Number} userId
         */
        $openManufacturer: function (userId) {
            if (!userId) {
                return;
            }

            var self = this;

            this.fireEvent('manufacturerOpenBegin', [this, userId]);

            QUIPanelUtils.openUserPanel(userId).then(function () {
                self.fireEvent('manufacturerOpenEnd', [this, userId, self.$ManufacturerPanel]);
            });
        },

        /**
         * event: on grid click
         */
        $gridClick: function () {
            //var selected = this.$Grid.getSelectedData();
            //var Delete   = this.$Grid.getButtons().filter(function (Btn) {
            //    return Btn.getAttribute('name') === 'delete';
            //})[0];
            //
            //Delete.disable();
            //
            //if (selected.length === 1) {
            //    Delete.enable();
            //}
        },

        /**
         * opens the add manufacturer window
         */
        openAddWindow: function () {
            var self = this;

            require([
                'package/quiqqer/erp/bin/backend/controls/manufacturers/create/ManufacturerWindow'
            ], function (ManufacturerWindow) {
                new ManufacturerWindow({
                    events: {
                        onSubmit: function (Instance, manufacturerId) {
                            self.refresh();
                            self.$openManufacturer(manufacturerId);
                        }
                    }
                }).open();
            });
        },

        ///**
        // * opens the manufacturer delete window
        // */
        //openDeleteWindow: function () {
        //    var self = this;
        //
        //    new QUIConfirm({
        //        title      : QUILocale.get(lg, 'manufacturer.window.delete.title'),
        //        text       : QUILocale.get(lg, 'manufacturer.window.delete.text'),
        //        information: QUILocale.get(lg, 'manufacturer.window.delete.information'),
        //        icon       : 'fa fa-trash',
        //        texticon   : 'fa fa-trash',
        //        maxHeight  : 400,
        //        maxWidth   : 600,
        //        autoclose  : false,
        //        events     : {
        //            onSubmit: function (Win) {
        //                Win.Loader.show();
        //
        //                var selected = self.$Grid.getSelectedData().map(function (entry) {
        //                    return entry.id;
        //                });
        //
        //                Users.deleteUsers(selected).then(function () {
        //                    Win.close();
        //                    self.refresh();
        //                });
        //            }
        //        }
        //    }).open();
        //},

        //region filter

        /**
         * Toggle the filter
         */
        toggleFilter: function () {
            var FilterContainer = this.getElm().getElement('.quiqqer-erp-manufacturers-administration-search-filter');

            if (FilterContainer.getStyle('display') === 'none') {
                this.openFilter();
            } else {
                this.closeFilter();
            }
        },

        /**
         * Open the filter
         */
        openFilter: function () {
            var self            = this,
                FilterContainer = this.getElm().getElement('.quiqqer-erp-manufacturers-administration-search-filter');

            FilterContainer.setStyle('position', 'absolute');
            FilterContainer.setStyle('opacity', 0);
            FilterContainer.setStyle('overflow', 'hidden');

            // reset
            FilterContainer.setStyle('display', null);
            FilterContainer.setStyle('height', null);
            FilterContainer.setStyle('paddingBottom', null);
            FilterContainer.setStyle('paddingTop', null);

            var height = FilterContainer.getSize().y;

            FilterContainer.setStyle('height', 0);
            FilterContainer.setStyle('paddingBottom', 0);
            FilterContainer.setStyle('paddingTop', 0);
            FilterContainer.setStyle('position', null);

            moofx(FilterContainer).animate({
                height       : height,
                marginTop    : 20,
                opacity      : 1,
                paddingBottom: 10,
                paddingTop   : 10
            }, {
                duration: 300,
                callback: function () {
                    self.resize();
                }
            });
        },

        /**
         * Close the filter
         */
        closeFilter: function () {
            var self            = this,
                FilterContainer = this.getElm().getElement('.quiqqer-erp-manufacturers-administration-search-filter');

            moofx(FilterContainer).animate({
                height       : 0,
                marginTop    : 0,
                opacity      : 1,
                paddingBottom: 0,
                paddingTop   : 0
            }, {
                duration: 300,
                callback: function () {
                    FilterContainer.setStyle('display', 'none');

                    FilterContainer.setStyle('height', null);
                    FilterContainer.setStyle('paddingBottom', null);
                    FilterContainer.setStyle('paddingTop', null);

                    self.resize();
                }
            });
        }

        //endregion
    });
});
