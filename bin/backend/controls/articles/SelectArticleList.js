/**
 * Lists articles that are selectable via checkbox. Only calculates selected articles.
 *
 * @module package/quiqqer/erp/bin/backend/controls/articles/SelectArticleList
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onCalc [self, {Object} calculation]
 * @event onArticleSelect [self, {Object} Article]
 * @event onArticleUnSelect [self, {Object} Article]
 * @event onArticleReplaceClick [self, {Object} Article]
 */
define('package/quiqqer/erp/bin/backend/controls/articles/SelectArticleList', [

    'package/quiqqer/erp/bin/backend/controls/articles/ArticleList',
    'css!package/quiqqer/erp/bin/backend/controls/articles/SelectArticleList.css'

], function (ArticleList) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: ArticleList,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/SelectArticleList',

        Binds: [
            '$onArticleSelect',
            '$onArticleUnSelect',
            '$onInject',
            'getSelectedArticles',
            '$addArticle',
            '$getArticleDataForCalculation'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            //this.parent();
            this.getElm().addClass('quiqqer-erp-backend-erpItems-selectlist');
        },

        /**
         * Get all selected articles
         *
         * @return {[]}
         */
        getSelectedArticles: function () {
            var articles = [];

            this.$articles.forEach(function (Article) {
                if (Article.isSelected()) {
                    articles.push(Article);
                }
            });

            return articles;
        },

        /**
         * event : on article delete
         *
         * @param {Object} Article
         */
        $onArticleSelect: function (Article) {
            this.$selectedArticle = Article;
            this.fireEvent('articleSelect', [this, Article]);
            this.$calc();
        },

        /**
         * event : on article delete
         *
         * @param Article
         */
        $onArticleUnSelect: function (Article) {
            this.$selectedArticle = null;
            this.fireEvent('articleUnSelect', [this, Article]);
            this.$calc();
        },

        /**
         * Get article data used for calculation
         *
         * @return {Array}
         */
        $getArticleDataForCalculation: function () {
            var returnSelectedOnly = true;

            // Only return selected articles if all articles have been calculated once
            this.$articles.forEach(function (Article) {
                if (!Article.getCalculations()) {
                    returnSelectedOnly = false;
                }
            });

            if (!returnSelectedOnly) {
                return this.parent();
            }

            return this.getSelectedArticles().map(function (Article) {
                return Article.getAttributes();
            });
        },

        /**
         * Get internal article list
         *
         * @return {[]}
         */
        $getArticles: function () {
            return this.getSelectedArticles();
        }
    });
});
