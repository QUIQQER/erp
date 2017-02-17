/**
 * @module package/quiqqer/erp/bin/backend/controls/Panel
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/sitemap/Map
 * @require qui/controls/sitemap/Item
 * @require Ajax
 */
define('package/quiqqer/erp/bin/backend/controls/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'utils/Panels',
    'Ajax',
    'Locale'

], function (QUI, QUIPanel, QUISiteMap, QUISitemapItem, PanelUtils, QUIAjax, QUILocale) {
    "use strict";

    return new Class({
        Extends: QUIPanel,
        Type: 'package/quiqqer/erp/bin/backend/controls/Panel',

        Bind: [
            '$onCreate'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: 'Shop',
                icon: 'fa fa-shopping-cart'
            });

            this.$Map = null;

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            this.getContent().setStyles({
                paddingLeft: 0
            });

            this.$Map = new QUISiteMap({
                styles: {
                    margin: 0
                }
            });

            this.Loader.show();

            QUIAjax.get('package_quiqqer_erp_ajax_panel_list', function (result) {
                var i, len, data, params;

                var onClick = function (Item) {
                    if (!Item.getAttribute('panel')) {
                        return;
                    }

                    require([Item.getAttribute('panel')], function (PanelCls) {
                        var Panel = new PanelCls();

                        if (instanceOf(Panel, QUIPanel)) {
                            PanelUtils.openPanelInTasks(Panel);
                        }

                    }, function (err) {
                        console.error(err);
                    });
                };

                for (i = 0, len = result.length; i < len; i++) {
                    params = {
                        events: {
                            onClick: onClick
                        }
                    };

                    data = result[i];

                    if ("icon" in data) {
                        params.icon = data.icon;
                    }

                    if ("text" in data) {
                        if (typeOf(data.text) === 'array') {
                            data.text = QUILocale.get(data.text[0], data.text[1]);
                        }

                        params.text = data.text;
                    }

                    if ("panel" in data) {
                        params.panel = data.panel;
                    }


                    this.$Map.appendChild(
                        new QUISitemapItem(params)
                    );
                }

                this.$Map.inject(this.getContent());
                this.Loader.hide();
            }.bind(this), {
                'package': 'quiqqer/erp'
            });

            //
            // this.$Map.appendChild(
            //     new QUISitemapItem({
            //         icon: 'fa fa-money',
            //         text: 'Rechnungen (Journal)'
            //     })
            // );
            //
            // this.$Map.appendChild(
            //     new QUISitemapItem({
            //         icon: 'fa fa-money',
            //         text: 'Rechnungen erstellen'
            //     })
            // );
            //
            // this.$Map.appendChild(
            //     new QUISitemapItem({
            //         icon: 'fa fa-shopping-bag',
            //         text: 'Produkte'
            //     })
            // );
            //
            // this.$Map.appendChild(
            //     new QUISitemapItem({
            //         icon: 'fa fa-sitemap',
            //         text: 'Kategorien'
            //     })
            // );
            //
            // this.$Map.inject(this.getContent());
        }
    });
});