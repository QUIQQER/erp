/**
 * @module package/quiqqer/erp/bin/backend/controls/Comments
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

                return {
                    date     : date,
                    time     : Formatter.format(date),
                    message  : entry.message,
                    type     : type,
                    timestamp: entry.time,
                    id       : entry.id,
                    source   : entry.source
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

                title = QUILocale.get(lg, 'comments.comment.title', {
                    source: entry.source
                });

                group[day].data.push({
                    time     : entry.time,
                    message  : entry.message,
                    type     : entry.type,
                    timestamp: entry.timestamp,
                    id       : entry.id,
                    source   : entry.source,
                    title    : entry.source !== '' ? title : ''
                });
            }

            // parse for mustache
            comments = [];

            var sortComments = function (a, b) {
                return a.timestamp - b.timestamp;
            };

            for (i in group) {
                if (group.hasOwnProperty(i)) {
                    // reverse comments
                    group[i].data = group[i].data.sort(sortComments).reverse();

                    comments.push(group[i]);
                }
            }

            this.$Elm.set({
                html: Mustache.render(template, {
                    comments      : comments,
                    textNoComments: QUILocale.get(lg, 'comments.message.no.comments')
                })
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
        }
    });
});
