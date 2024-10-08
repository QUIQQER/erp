/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/Text
 * @author www.pcsg.de (Henning Leutz)
 *
 * Text Produkt
 * - Dieses "Produkt" benhaltet nur text und hat keine Summe oder Preise
 * - Dieses Produkt wird verwendet für Hinweise auf der Rechnung
 */
define('package/quiqqer/erp/bin/backend/controls/articles/Text', [

    'package/quiqqer/erp/bin/backend/controls/articles/Article',
    'qui/controls/buttons/Button',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/articles/Text.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/Text.css'

], function(Article, QUIButton, QUILocale, Mustache, template) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: Article,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/Text',

        Binds: [
            '$onEditTitle',
            '$onEditDescription'
        ],

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                'class': 'QUI\\ERP\\Accounting\\Invoice\\Articles\\Text'
            });
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function() {
            this.$Elm = new Element('div');

            this.$Elm.addClass('quiqqer-erp-backend-erpArticleText');

            this.$Elm.set({
                html: Mustache.render(template, {
                    buttonReplace: QUILocale.get(lg, 'articleList.article.button.replace'),
                    buttonDelete: QUILocale.get(lg, 'articleList.article.button.delete')
                }),
                events: {
                    click: this.select
                }
            });

            this.$Position = this.$Elm.getElement('.quiqqer-erp-backend-erpArticleText-pos');
            this.$Text = this.$Elm.getElement('.quiqqer-erp-backend-erpArticleText-text');
            this.$Buttons = this.$Elm.getElement('.quiqqer-erp-backend-erpArticleText-buttons');

            this.$Loader = new Element('div', {
                html: '<span class="fa fa-spinner fa-spin"></span>',
                styles: {
                    background: '#fff',
                    display: 'none',
                    left: 0,
                    padding: 10,
                    position: 'absolute',
                    top: 0,
                    width: '100%'
                }
            }).inject(this.$Position);

            new Element('span').inject(this.$Position);

            if (this.getAttribute('position')) {
                this.setPosition(this.getAttribute('position'));
            }

            // text nodes
            this.$Title = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticleText-text-title cell-editable'
            }).inject(this.$Text);

            this.$Description = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticleText-text-description cell-editable'
            }).inject(this.$Text);

            this.$Title.addEvent('click', this.$onEditTitle);
            this.$Description.addEvent('click', this.$onEditDescription);

            this.setTitle(this.getAttribute('title'));
            this.setDescription(this.getAttribute('description'));

            this.$Buttons.getElement('[name="replace"]').addEvent('click', this.$onReplaceClick);
            this.$Buttons.getElement('[name="delete"]').addEvent('click', this.openDeleteDialog);

            this.$created = true;

            return this.$Elm;
        },

        /**
         * Calculates nothing
         * Text Article has no prices
         *
         * @return {Promise}
         */
        calc: function() {
            return Promise.resolve();
        },

        /**
         * Set the product title
         *
         * @param {String} title
         */
        setTitle: function(title) {
            this.setAttribute('title', title);
            this.$Title.set('html', title);

            if (title === '') {
                this.$Title.set('html', '&nbsp;');
            }
        },

        /**
         * Set the product description
         *
         * @param {String} description
         */
        setDescription: function(description) {
            this.setAttribute('description', description);
            this.$Description.set('html', description);

            if (description === '') {
                this.$Description.set('html', '&nbsp;');
            }
        },

        /**
         * Set the product quantity
         *
         * @return {Promise}
         */
        setQuantity: function() {
            return Promise.resolve();
        },

        /**
         * Set the product unit price
         *
         */
        setUnitPrice: function() {
            return Promise.resolve();
        },

        /**
         * Set the product unit price
         **/
        setVat: function() {
            return Promise.resolve();
        },

        /**
         * Show the loader
         */
        showLoader: function() {
            this.$Loader.setStyle('display', null);
        },

        /**
         * Hide the loader
         */
        hideLoader: function() {
            this.$Loader.setStyle('display', 'none');
        }
    });
});
