/**
 *
 * @event manufacturerOpenBegin [self, userId]
 * @event onManufacturerOpen [self, userId, Panel]
 * @event onManufacturerOpenEnd [self, userId, Panel]
 * @event onListOpen [self]
 */
define('package/quiqqer/erp/bin/backend/controls/manufacturers/Administration', [

    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'qui/controls/toolbar/Bar',
    'utils/Panels',

    'package/quiqqer/erp/bin/backend/Manufacturers',
    'package/quiqqer/erp/bin/backend/controls/elements/TimeFilter',

    'controls/grid/Grid',
    'Mustache',
    'Locale',
    'Ajax',
    'Users',

    'text!package/quiqqer/erp/bin/backend/controls/manufacturers/Administration.html',
    'css!package/quiqqer/erp/bin/backend/controls/manufacturers/Administration.css'

], function (QUIControl, QUIConfirm, QUIButton, QUIToolbar, QUIPanelUtils, Manufacturers, TimeFilter, Grid, Mustache, QUILocale, QUIAjax, Users,
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
            toolbar       : null,
            page          : 1,
            perPage       : 50,
            editable      : true,
            submittable   : false,
            add           : true,
            manufacturerId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$SearchForm           = null;
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

            this.$Elm = document.createElement('div');
            this.$Elm.className = 'quiqqer-erp-manufacturers-administration';
            this.$Elm.innerHTML = Mustache.render(template, {
                searchPlaceholder: QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.searchPlaceholder'),
                filterLabel      : QUILocale.get(lg, 'controls.manufacturers.Administration.btn.filter'),
                filterTitle      : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterTitle'),
                filterUserId     : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterUserId'),
                filterUsername   : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterUsername'),
                filterFirstname  : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterFirstname'),
                filterLastname   : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterLastname'),
                filterEmail      : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterEmail'),
                filterCompany    : QUILocale.get(lg, 'controls.manufacturers.Administration.tpl.filterCompany')
            });

            this.$SearchForm = this.$Elm.querySelector('[data-name="search-form"]');
            this.$GridContainer = this.$Elm.querySelector('[data-name="grid"]');
            this.$SearchInput = this.$Elm.querySelector('[data-name="search-input"]');
            this.$SubmitButton = this.$Elm.querySelector('[data-name="search-submit"]');
            this.$Filters = this.$Elm.querySelector('[data-name="filters"]');
            this.$Filters.id = 'manufacturer-filters-' + this.getId();
            this.$SearchForm.id = 'manufacturer-search-' + this.getId();
            for (const Input of this.$Filters.querySelectorAll('[data-name="filter-field"]')) {
                Input.setAttribute('form', this.$SearchForm.id);
            }
            document.body.appendChild(this.$Filters);
            this.$Filters.addEventListener('change', () => this.$submitSearch());
            this.$filterOutside = event => {
                if (!this.$Filters.contains(event.target) && !this.$FilterButton.contains(event.target)) {
                    this.closeFilter();
                }
            };
            this.$filterKeydown = event => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeFilter(true);
                }
            };
            this.$filterReposition = () => this.$positionFilter();

            this.$Toolbar = this.getAttribute('toolbar');
            this.$ownsToolbar = !this.$Toolbar;
            if (this.$ownsToolbar) {
                this.$Toolbar = new QUIToolbar().inject(this.$Elm, 'top');
            }
            this.$Toolbar.getElm().classList.add('quiqqer-erp-manufacturers-toolbar');
            this.$Toolbar.appendChild(this.$SearchForm);
            this.$periodEnabled = false;
            this.$PeriodToggle = document.createElement('label');
            this.$PeriodToggle.className = 'quiqqer-erp-manufacturers-period-toggle';
            this.$PeriodEnabled = document.createElement('input');
            this.$PeriodEnabled.type = 'checkbox';
            this.$PeriodEnabled.dataset.name = 'period-enabled';
            this.$PeriodToggle.append(
                this.$PeriodEnabled,
                document.createTextNode(QUILocale.get(lg, 'controls.manufacturers.Administration.filter.period'))
            );
            this.$PeriodEnabled.addEventListener('change', () => {
                this.$periodEnabled = this.$PeriodEnabled.checked;
                this.$TimeFilter[this.$periodEnabled ? 'enable' : 'disable']();
                this.$submitSearch();
            });
            this.$Toolbar.appendChild(this.$PeriodToggle);
            this.$TimeFilter = new TimeFilter({
                events: {
                    onChange: () => {
                        if (this.$Grid && this.$periodEnabled) {
                            this.$submitSearch();
                        }
                    }
                }
            });
            this.$Toolbar.appendChild(this.$TimeFilter);
            this.$TimeFilter.getElm().classList.add('quiqqer-erp-manufacturers-period');
            this.$TimeFilter.disable();
            this.$FilterControl = new QUIButton({
                name: 'filter',
                'class': 'quiqqer-erp-manufacturers-administration-filter-button',
                text: QUILocale.get(lg, 'controls.manufacturers.Administration.btn.filter'),
                textimage: 'fa fa-filter',
                events: {onClick: () => this.toggleFilter()}
            });
            this.$Toolbar.appendChild(this.$FilterControl);
            this.$FilterButton = this.$FilterControl.getElm();
            this.$FilterButton.tabIndex = 0;
            this.$FilterButton.style.removeProperty('outline');
            this.$FilterButton.firstElementChild?.setAttribute('aria-hidden', 'true');
            this.$FilterButton.setAttribute('aria-expanded', 'false');
            this.$FilterButton.setAttribute('aria-haspopup', 'dialog');
            this.$FilterButton.setAttribute('aria-controls', this.$Filters.id);
            const Arrow = document.createElement('span');
            Arrow.className = 'fa fa-chevron-down';
            Arrow.setAttribute('aria-hidden', 'true');
            this.$FilterButton.appendChild(Arrow);

            this.$SearchForm.addEventListener('submit', event => {
                event.preventDefault();
                this.$submitSearch();
            });
            this.$SearchInput.addEventListener('input', () => {
                if (!this.$SearchInput.value) {
                    this.$submitSearch();
                }
            });

            // create grid
            this.$Container = document.createElement('div');
            this.$GridContainer.appendChild(this.$Container);

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
                header   : QUILocale.get('quiqqer/core', 'id'),
                dataIndex: 'id',
                dataType : 'integer',
                width    : 100
            });

            /*{
                header   : QUILocale.get('quiqqer/core', 'username'),
                dataIndex: 'username',
                dataType : 'integer',
                width    : 150,
                editable : editable,
                className: editable ? 'clickable' : ''
            }*/

            columnModel.push({
                header   : QUILocale.get('quiqqer/core', 'company'),
                dataIndex: 'company',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/core', 'firstname'),
                dataIndex: 'firstname',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/core', 'lastname'),
                dataIndex: 'lastname',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/core', 'email'),
                dataIndex: 'email',
                dataType : 'string',
                width    : 150,
            });

            columnModel.push({
                header   : QUILocale.get('quiqqer/core', 'group'),
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
                header   : QUILocale.get('quiqqer/core', 'c_date'),
                dataIndex: 'regdate',
                dataType : 'date',
                width    : 150
            });

            const buttons = this.getAttribute('add') ? [{
                name: 'add',
                textimage: 'fa fa-plus',
                text: QUILocale.get(lg, 'controls.manufacturers.Administration.btn.create'),
                events: {onClick: self.openAddWindow}
            }] : [];

            this.$Grid = new Grid(this.$Container, {
                buttons: buttons,
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

            const Create = this.$Grid.getButton('add');

            if (Create) {
                const Button = Create.getElm();
                Button.style.removeProperty('outline');
                Button.firstElementChild?.setAttribute('aria-hidden', 'true');
            }

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
            this.closeFilter();
            this.$Filters?.remove();
            this.$SearchForm?.remove();
            this.$FilterControl?.destroy();
            this.$PeriodToggle?.remove();
            this.$TimeFilter?.destroy();
            if (this.$ownsToolbar) {
                this.$Toolbar?.destroy();
            }
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
                return entry.id;
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
                this.$Grid.setHeight(size.y - 20),
                this.$Grid.setWidth(size.x - 20)
            ]);
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            var self    = this,
                options = this.$Grid.options,
                Form    = this.$SearchForm;

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
                    ...this.$getPeriodFilters()
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

        $getPeriodFilters: function () {
            if (!this.$periodEnabled) {
                return {regdate_from: '', regdate_to: ''};
            }

            const period = this.$TimeFilter.getValue();
            const format = timestamp => {
                const DateValue = new Date(timestamp * 1000);
                return [
                    DateValue.getFullYear(),
                    String(DateValue.getMonth() + 1).padStart(2, '0'),
                    String(DateValue.getDate()).padStart(2, '0')
                ].join('-');
            };
            return {regdate_from: format(period.from), regdate_to: format(period.to)};
        },

        $submitSearch: function () {
            this.$Grid.options.page = 1;
            return this.refresh();
        },

        /**
         * Toggle the filter
         */
        toggleFilter: function () {
            if (this.$Filters.hidden) {
                this.openFilter();
            } else {
                this.closeFilter();
            }
        },

        /**
         * Open the filter
         */
        openFilter: function () {
            this.$Filters.hidden = false;
            this.$FilterButton.setAttribute('aria-expanded', 'true');
            this.$positionFilter();
            document.addEventListener('pointerdown', this.$filterOutside);
            document.addEventListener('keydown', this.$filterKeydown);
            document.addEventListener('scroll', this.$filterReposition, true);
            window.addEventListener('resize', this.$filterReposition);
            this.$Filters.querySelector('[data-name="filter-field"]')?.focus();
        },

        /**
         * Close the filter
         */
        closeFilter: function (restoreFocus = false) {
            if (!this.$Filters) {
                return;
            }

            this.$Filters.hidden = true;
            this.$FilterButton?.setAttribute('aria-expanded', 'false');
            document.removeEventListener('pointerdown', this.$filterOutside);
            document.removeEventListener('keydown', this.$filterKeydown);
            document.removeEventListener('scroll', this.$filterReposition, true);
            window.removeEventListener('resize', this.$filterReposition);
            if (restoreFocus) {
                this.$FilterButton.focus();
            }
        },

        $positionFilter: function () {
            const button = this.$FilterButton.getBoundingClientRect();
            const overlay = this.$Filters.getBoundingClientRect();
            const left = Math.max(8, Math.min(button.right - overlay.width, window.innerWidth - overlay.width - 8));
            const top = button.bottom + overlay.height + 8 <= window.innerHeight
                ? button.bottom + 8
                : Math.max(8, button.top - overlay.height - 8);
            this.$Filters.style.left = left + 'px';
            this.$Filters.style.top = top + 'px';
        }

        //endregion
    });
});
