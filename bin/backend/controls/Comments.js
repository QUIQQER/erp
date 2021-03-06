/**
 * @module package/quiqqer/erp/bin/backend/controls/Comments
 * @author www.pcsg.de (Henning Leutz
 *
 * @event onEdit [self, Comment Node, data]
 *
 * Comments / History Display
 */
define('package/quiqqer/erp/bin/backend/controls/Comments', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',

    'text!package/quiqqer/erp/bin/backend/controls/Comments.html',
    'css!package/quiqqer/erp/bin/backend/controls/Comments.css'

], function (QUI, QUIControl, Mustache, QUILocale, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/Comments',

        Binds: [
            '$onCreate'
        ],

        options: {
            comments: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$filter   = false;
            this.$comments = {};

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        /**
         * Create the DomNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-erp-comments');
            this.unserialize(this.getAttribute('comments'));

            return this.$Elm;
        },

        /**
         * insert / set comments
         *
         * @param {String|Object} comments
         */
        unserialize: function (comments) {
            if (typeOf(comments) === 'string') {
                try {
                    comments = JSON.decode(comments);
                } catch (e) {
                }
            }

            if (!comments) {
                return;
            }

            var Formatter = this.$getFormatter();

            comments = comments.map(function (entry) {
                var date = new Date(entry.time * 1000),
                    type = 'fa fa-comment';

                if (typeof entry.type !== 'undefined') {
                    switch (entry.type) {
                        case 'history':
                            type = 'fa fa-history';
                            break;

                        case 'transaction':
                            type = 'fa fa-money';
                            break;
                    }
                }

                if (typeof entry.sourceIcon !== 'undefined' && entry.sourceIcon !== '') {
                    type = entry.sourceIcon;
                }

                if (typeof entry.source === 'undefined') {
                    entry.source = '';
                }

                if (typeof entry.id === 'undefined') {
                    entry.id = '';
                }

                if (typeof entry.editable === 'undefined') {
                    entry.editable = false;
                }

                return {
                    date     : date,
                    time     : Formatter.format(date),
                    message  : entry.message,
                    type     : type,
                    timestamp: entry.time,
                    id       : entry.id,
                    source   : entry.source,
                    editable : entry.editable
                };
            });

            // grouping
            var i, len, day, date, entry, title;

            var group        = {};
            var DayFormatter = this.$getDayFormatter();

            for (i = 0, len = comments.length; i < len; i++) {
                entry = comments[i];
                date  = entry.date;
                day   = DayFormatter.format(date);

                if (typeof group[day] === 'undefined') {
                    group[day] = {
                        day : day,
                        data: []
                    };
                }

                title = '';

                if (entry.source !== '') {
                    var packageTitle = QUILocale.get(entry.source, 'package.title');

                    title = QUILocale.get(lg, 'comments.comment.title', {
                        source: packageTitle + ' (' + entry.source + ')'
                    });
                }

                group[day].data.push({
                    time     : entry.time,
                    message  : entry.message,
                    type     : entry.type,
                    timestamp: entry.timestamp,
                    id       : entry.id,
                    source   : entry.source,
                    title    : entry.source !== '' ? title : '',
                    editable : entry.editable
                });
            }

            this.$comments = group;
            this.refresh();
        },

        /**
         * refresh the display
         */
        refresh: function () {
            var i, data, realData, commentEntries;
            var self     = this;
            var comments = [];

            var sortComments = function (a, b) {
                return a.timestamp - b.timestamp;
            };

            var commentClone = Object.clone(this.$comments);

            var filterComments = function (entry) {
                var message = entry.message.toLowerCase();
                var type    = entry.type.toLowerCase();
                var id      = entry.id.toLowerCase();

                if (message.indexOf(self.$filter) === -1 &&
                    type.indexOf(self.$filter) === -1 &&
                    id.indexOf(self.$filter) === -1
                ) {
                    return;
                }

                this.push(entry);
            };

            for (i in commentClone) {
                if (!commentClone.hasOwnProperty(i)) {
                    continue;
                }

                commentEntries = commentClone[i];
                data           = commentEntries.data;

                if (this.$filter) {
                    // check filter
                    data     = [];
                    realData = commentEntries.data; // copy

                    realData.forEach(filterComments.bind(data));
                }

                if (!data.length) {
                    continue;
                }

                // reverse comments
                commentClone[i].data = data.sort(sortComments).reverse();

                comments.push(commentClone[i]);
            }

            this.$Elm.set({
                html: Mustache.render(template, {
                    comments      : comments,
                    textNoComments: QUILocale.get(lg, 'comments.message.no.comments')
                })
            });

            this.$Elm.getElements('[data-editable]').addEvent('click', function (event) {
                var Parent = event.target;

                if (!Parent.hasClass('quiqqer-erp-comments-comment')) {
                    Parent = Parent.getParent('.quiqqer-erp-comments-comment');
                }

                var data = {
                    id    : Parent.get('data-id'),
                    source: Parent.get('data-source')
                };

                self.fireEvent('edit', [self, Parent, data]);
            });
        },

        /**
         * Return the date formatter
         *
         * @return {window.Intl.DateTimeFormat}
         */
        $getFormatter: function () {
            var locale = QUILocale.getCurrent();

            var options = {
                // year  : 'numeric',
                // month : '2-digit',
                // day   : '2-digit',
                hour  : '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };

            if (!locale.match('_')) {
                locale = locale.toLowerCase() + '_' + locale.toUpperCase();
            }

            locale = locale.replace('_', '-');

            try {
                return window.Intl.DateTimeFormat(locale, options);
            } catch (e) {
                return window.Intl.DateTimeFormat('de-DE', options);
            }
        },

        /**
         * Return the date day formatter
         *
         * @return {window.Intl.DateTimeFormat}
         */
        $getDayFormatter: function () {
            var locale = QUILocale.getCurrent();

            var options = {
                year : 'numeric',
                month: '2-digit',
                day  : '2-digit'
            };

            if (!locale.match('_')) {
                locale = locale.toLowerCase() + '_' + locale.toUpperCase();
            }

            locale = locale.replace('_', '-');

            try {
                return window.Intl.DateTimeFormat(locale, options);
            } catch (e) {
                return window.Intl.DateTimeFormat('de-DE', options);
            }
        },

        //region filter

        /**
         * shows only comments which fits to the filter
         *
         * @param {String} value
         */
        filter: function (value) {
            this.$filter = value.toString().toLowerCase();
            this.refresh();
        },

        /**
         * Clears the filter
         */
        clearFilter: function () {
            this.$filter = false;
            this.refresh();
        }

        //endregion
    });
});
