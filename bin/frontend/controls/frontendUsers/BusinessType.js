/**
 * @module package/quiqqer/erp/bin/frontend/controls/frontendUsers/BusinessType
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/frontend/controls/frontendUsers/BusinessType', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/frontend/controls/frontendUsers/BusinessType',

        Binds: [
            '$onChange',
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Select  = null;
            this.$Company = null;

            this.$CompanyLabel = null;
            this.$VatSection   = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            this.$Select = this.getElm();
            this.$Select.addEvent('change', this.$onChange);

            var Profile = this.$Select.getParent(
                '.quiqqer-frontendUsers-controls-profile-control'
            );

            this.$Company = Profile.getElement('[name="company"]');

            this.$CompanyLabel = this.$Company.getParent('label');
            this.$VatSection   = Profile.getElement('.quiqqer-erp-userProfile-vat');

            this.$onChange();
        },

        /**
         * event: select change
         */
        $onChange: function () {
            var value = this.$Select.value;

            if (value === 'b2c') {
                this.hideElement(this.$CompanyLabel);
                this.hideElement(this.$VatSection);
                return;
            }

            this.showElement(this.$CompanyLabel);
            this.showElement(this.$VatSection);
        },

        /**
         * Show an element
         *
         * @param {HTMLElement} Node
         * @return {Promise}
         */
        showElement: function (Node) {
            return new Promise(function (resolve) {
                Node.setStyle('display', 'none');

                Node.setStyles({
                    height  : null,
                    margin  : null,
                    overflow: 'hidden',
                    padding : null,
                    position: 'relative'
                });

                var size = Node.getComputedSize();

                Node.setStyle('height', 0);
                Node.setStyle('display', null);

                moofx(Node).animate({
                    opacity: 1,
                    height : size.height
                }, {
                    callback: function () {
                        Node.style.height = null;
                        resolve();
                    }
                });
            });
        },

        /**
         * Show an element
         *
         * @param {HTMLElement} Node
         * @return {Promise}
         */
        hideElement: function (Node) {
            return new Promise(function (resolve) {
                Node.setStyles({
                    overflow: 'hidden',
                    position: 'relative'
                });

                moofx(Node).animate({
                    height : 0,
                    opacity: 0,
                    margin : 0,
                    padding: 0
                }, {
                    callback: function () {
                        Node.setStyles({
                            height : null,
                            display: 'none'
                        });

                        resolve();
                    }
                });
            });
        }
    });
});
