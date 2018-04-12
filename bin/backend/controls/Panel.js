/**
 * @module package/quiqqer/erp/bin/backend/controls/Panel
 * @author www.pcsg.de (Henning Leutz)
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
        Type   : 'package/quiqqer/erp/bin/backend/controls/Panel',

        Bind: [
            '$onCreate',
            '$itemClick'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: 'Shop',
                icon : 'fa fa-shopping-cart'
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
                this.$Map.inject(this.getContent());
                this.$appendItems(result.items, this.$Map);

                this.Loader.hide();
            }.bind(this), {
                'package': 'quiqqer/erp'
            });
        },

        /**
         * render the maps
         *
         * @param items
         * @param Parent
         */
        $appendItems: function (items, Parent) {
            var i, len, item, text, Item;

            for (i = 0, len = items.length; i < len; i++) {
                item = items[i];
                text = item.text;

                if (typeOf(text) === 'array') {
                    item.text = QUILocale.get(text[0], text[1]);
                }

                Item = new QUISitemapItem(item);
                Item.addEvent('click', this.$itemClick);

                Parent.appendChild(Item);

                if (typeof item.items !== 'undefined' && item.items.length) {
                    this.$appendItems(item.items, Item);
                }

                if (item.opened) {
                    Item.open();
                }
            }
        },

        /**
         * event: item click
         *
         * @param Item
         */
        $itemClick: function (Item) {
            var needle = Item.getAttribute('require');

            if (needle === false) {
                return;
            }

            var icon = Item.getAttribute('icon');

            Item.removeIcon(icon);
            Item.setAttribute('icon', 'fa fa-spinner fa-spin');

            require([needle], function (cls) {
                var Instance;

                if (typeOf(cls) === 'class') {
                    Instance = new cls();
                }

                if (Instance instanceof QUIPanel) {
                    PanelUtils.openPanelInTasks(Instance);

                    Item.removeIcon('fa-spinner');
                    Item.setAttribute('icon', icon);
                    return;
                }

                console.log(typeOf(cls));
            });
        }
    });
});