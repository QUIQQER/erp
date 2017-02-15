/**
 * @module package/quiqqer/erp/bin/controls/Panel
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/sitemap/Map
 * @require qui/controls/sitemap/Item
 * @require Ajax
 */
define('package/quiqqer/erp/bin/controls/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'Ajax',
    'Locale'

], function (QUI, QUIPanel, QUISiteMap, QUISitemapItem, QUIAjax, QUILocale) {
    "use strict";

    return new Class({
        Extends: QUIPanel,
        Type: 'package/quiqqer/erp/bin/controls/Panel',

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

                for (i = 0, len = result.length; i < len; i++) {
                    params = {};
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