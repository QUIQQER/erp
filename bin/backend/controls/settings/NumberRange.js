/**
 * @module package/quiqqer/erp/bin/backend/controls/settings/NumberRange
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/settings/NumberRange', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',
    'Ajax',
    'Locale'

], function (QUI, QUIControl, Grid, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/NumberRange',

        Bind: [
            '$onImport',
            'refresh'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Refresh the new data
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_settings_numberRanges_list', function (result) {
                    self.$Grid.setData({
                        data: result
                    });

                    resolve(result);
                }, {
                    'package': 'quiqqer/erp'
                });
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();

            this.$Elm = new Element('div', {
                'class': 'field-container-field',
                styles : {
                    border : 0,
                    padding: 0
                }
            }).wraps(this.$Input);

            var Container = new Element('div', {
                styles: {
                    width: '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                height        : 200,
                filterInput   : false,
                editable      : true,
                editondblclick: true,
                columnModel   : [{
                    header   : QUILocale.get(lg, 'control.number.ranges.grid.ranges'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'control.number.ranges.grid.next'),
                    dataIndex: 'range',
                    dataType : 'number',
                    width    : 200,
                    editable : true
                }, {
                    dataIndex: 'class',
                    dataType : 'string',
                    hidden   : true
                }]
            });

            this.$Grid.addEvents({
                onRefresh     : this.refresh,
                onEditComplete: function (data) {
                    var row     = data.row,
                        rowData = self.$Grid.getDataByRow(row);

                    self.setAutoincrement(rowData.class, data.input.value);
                }
            });

            new Element('div', {
                'class': 'field-container-item-desc',
                html   : QUILocale.get(lg, 'control.number.ranges.description'),
                styles : {
                    display: 'block',
                    padding: 10,
                    width  : '100%'
                }
            }).inject(this.$Elm);

            this.refresh();
        },

        /**
         *
         * @param {String} className
         * @param {Number} newIndex
         */
        setAutoincrement: function (className, newIndex) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_erp_ajax_settings_numberRanges_set', resolve, {
                    'package': 'quiqqer/erp',
                    className: className,
                    newIndex : newIndex,
                    onError  : reject
                });
            });
        }
    });
});
