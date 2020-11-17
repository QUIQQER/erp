/**
 * @module package/quiqqer/erp/bin/backend/controls/settings/CustomerRequestWindowSettings
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/settings/CustomerRequestWindowSettings', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',
    'Ajax',
    'Locale'

], function (QUI, QUIControl, Grid, QUIAjax, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/CustomerRequestWindowSettings',

        Binds: [
            '$onImport',
            '$onChange'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Grid   = null;
            this.$values = [];

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import - control creation
         */
        $onImport: function () {
            this.$Input      = this.getElm();
            this.$Input.type = "hidden";

            this.$Elm = new Element('div', {
                'class': 'field-container-field',
                styles : {
                    border : 0,
                    padding: 0
                }
            }).wraps(this.$Input);

            var Container = new Element('div', {
                styles: {
                    width: 200
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                height        : 200,
                editable      : true,
                editondblclick: false,
                columnModel   : [{
                    header   : '&nbsp;',
                    dataIndex: 'status',
                    dataType : 'QUI',
                    width    : 60
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 60
                }, {
                    header   : QUILocale.get('quiqqer/areas', 'area.grid.areaname.title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get('quiqqer/areas', 'area.grid.areaname.countries'),
                    dataIndex: 'countries',
                    dataType : 'string',
                    width    : 300
                }]
            });

            // value parsing
            this.$values = this.$Input.value.split(',').map(function (entry) {
                return parseInt(entry);
            });

            this.$loadAreas();
        },

        /**
         * put the values to the hidden input field
         */
        $update: function () {
            this.$Input.value = this.$values.join(',');
        },

        /**
         * load the areas into the grid
         */
        $loadAreas: function () {
            var self = this;

            this.$Grid.disable();

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/areas/bin/classes/Handler',
                    'qui/controls/buttons/Switch'
                ], function (Handler, QUISwitch) {
                    var Areas = new Handler();

                    Areas.getList().then(function (areas) {
                        areas.data.forEach(function (entry, key) {
                            areas.data[key].status = new QUISwitch({
                                areaId: entry.id,
                                status: self.$values.indexOf(parseInt(entry.id)) !== -1,
                                events: {
                                    onChange: self.$onChange
                                }
                            });
                        });

                        self.$Grid.setData(areas);
                    }).then(function () {
                        self.$Grid.enable();

                        // resize
                        var FieldCell = self.$Grid.getElm().getParent('.field-container-field');

                        self.$Grid.setWidth(FieldCell.getSize().x);

                        resolve();
                    });
                });
            });
        },

        /**
         * on switch / status chaneg
         */
        $onChange: function () {
            var values = [];

            this.$Grid.getData().forEach(function (entry) {
                if (entry.status.getStatus()) {
                    values.push(entry.id);
                }
            });

            this.$values = values;
            this.$update();
        }
    });
});
