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
    'utils/Panels',
    'Mustache',
    'Locale',

    'text!package/quiqqer/erp/bin/backend/controls/Comments.html',
    'css!package/quiqqer/erp/bin/backend/controls/Comments.css'

], function(QUI, QUIControl, PanelUtils, Mustache, QUILocale, template) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/Comments',

        Binds: [
            '$onCreate',
            '$onEntryClick'
        ],

        options: {
            comments: false
        },

        initialize: function(options) {
            this.parent(options);

            this.$filter = false;
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
        create: function() {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-erp-comments');
            this.unserialize(this.getAttribute('comments'));

            return this.$Elm;
        },

        /**
         * empties the comment list
         */
        clear: function() {
            this.$comments = [];
            this.$Elm.set('html', '');
        },

        /**
         * insert / set comments
         *
         * @param {String|Object} comments
         */
        unserialize: function(comments) {
            if (typeOf(comments) === 'string') {
                try {
                    comments = JSON.decode(comments);
                } catch (e) {
                }
            }

            if (!comments) {
                return;
            }

            const Formatter = this.$getFormatter();

            comments = comments.map(function(entry) {
                let date = new Date(entry.time * 1000),
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

                if (typeof entry.objectHash === 'undefined') {
                    entry.objectHash = '';
                }

                if (typeof entry.editable === 'undefined') {
                    entry.editable = false;
                }

                return {
                    date: date,
                    time: Formatter.format(date),
                    message: entry.message,
                    type: type,
                    timestamp: entry.time,
                    id: entry.id,
                    source: entry.source,
                    editable: entry.editable,
                    objectHash: entry.objectHash
                };
            });

            // grouping
            let i, len, day, date, entry, title;

            const group = {};
            const DayFormatter = this.$getDayFormatter();

            for (i = 0, len = comments.length; i < len; i++) {
                entry = comments[i];
                date = entry.date;
                day = DayFormatter.format(date);

                if (typeof group[day] === 'undefined') {
                    group[day] = {
                        day: day,
                        data: []
                    };
                }

                title = '';

                if (entry.source !== '') {
                    let packageTitle = QUILocale.get(entry.source, 'package.title');

                    title = QUILocale.get(lg, 'comments.comment.title', {
                        source: packageTitle + ' (' + entry.source + ')'
                    });
                }

                group[day].data.push({
                    time: entry.time,
                    message: entry.message,
                    type: entry.type,
                    timestamp: entry.timestamp,
                    id: entry.id,
                    source: entry.source,
                    title: entry.source !== '' ? title : '',
                    editable: entry.editable,
                    objectHash: entry.objectHash
                });
            }

            this.$comments = group;
            this.refresh();
        },

        /**
         * refresh the display
         */
        refresh: function() {
            let i, data, realData, commentEntries;
            const self = this;
            const comments = [];

            const sortComments = function(a, b) {
                return a.timestamp - b.timestamp;
            };

            const commentClone = Object.clone(this.$comments);

            const filterComments = function(entry) {
                const message = entry.message.toLowerCase();
                const type = entry.type.toLowerCase();
                const id = entry.id.toLowerCase();

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
                data = commentEntries.data;

                if (this.$filter) {
                    // check filter
                    data = [];
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
                    comments: comments,
                    textNoComments: QUILocale.get(lg, 'comments.message.no.comments')
                })
            });

            this.$Elm.querySelectorAll('.quiqqer-erp-comments-comment').forEach((Comment) => {
                if (!Comment.get('data-object-hash')) {
                    return;
                }

                if (typeof QUIQQER_FRONTEND !== 'undefined') {
                    return;
                }

                Comment.addClass('quiqqer-erp-comments-comment--clickable');
                Comment.addEventListener('click', this.$onEntryClick);
            });

            this.$Elm.getElements('[data-editable]').addEvent('click', function(event) {
                let Parent = event.target;

                if (!Parent.hasClass('quiqqer-erp-comments-comment')) {
                    Parent = Parent.getParent('.quiqqer-erp-comments-comment');
                }

                const data = {
                    id: Parent.get('data-id'),
                    source: Parent.get('data-source'),
                    objectHash: Parent.get('data-object-hash')
                };

                self.fireEvent('edit', [
                    self,
                    Parent,
                    data
                ]);
            });
        },

        /**
         * Return the date formatter
         *
         * @return {window.Intl.DateTimeFormat}
         */
        $getFormatter: function() {
            let locale = QUILocale.getCurrent();

            const options = {
                // year  : 'numeric',
                // month : '2-digit',
                // day   : '2-digit',
                hour: '2-digit',
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
        $getDayFormatter: function() {
            let locale = QUILocale.getCurrent();

            const options = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
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
         * event: comment click
         *
         * @param event
         */
        $onEntryClick: function(event) {
            let Target = event.target;

            if (!Target.hasClass('quiqqer-erp-comments-comment')) {
                Target = Target.getParent('.quiqqer-erp-comments-comment');
            }

            switch (Target.get('data-source')) {
                case 'quiqqer/order':
                    require([
                        'package/quiqqer/order/bin/backend/controls/panels/Order'
                    ], (Order) => {
                        PanelUtils.openPanelInTasks(
                            new Order({
                                orderId: Target.get('data-object-hash')
                            })
                        );
                    });
                    return;

                case 'quiqqer/invoice':
                    require([
                        'package/quiqqer/invoice/bin/backend/controls/panels/Invoice'
                    ], (Invoice) => {
                        PanelUtils.openPanelInTasks(
                            new Invoice({
                                invoiceId: Target.get('data-object-hash')
                            })
                        );
                    });
                    return;

                case 'quiqqer/offer':
                    require([
                        'package/quiqqer/offers/bin/js/backend/controls/panels/Offer'
                    ], (Offer) => {
                        PanelUtils.openPanelInTasks(
                            new Offer({
                                offerId: Target.get('data-object-hash')
                            })
                        );
                    });
                    return;

                case 'quiqqer/purchasing':
                    require([
                        'package/quiqqer/purchasing/bin/js/backend/controls/panels/processes/Process'
                    ], (Process) => {
                        PanelUtils.openPanelInTasks(
                            new Process({
                                processId: Target.get('data-object-hash')
                            })
                        );
                    });
                    return;

                case 'quiqqer/salesorders':
                    require([
                        'package/quiqqer/salesorders/bin/js/backend/controls/panels/SalesOrder'
                    ], (SalesOrder) => {
                        PanelUtils.openPanelInTasks(
                            new SalesOrder({
                                salesOrderHash: Target.get('data-object-hash')
                            })
                        );
                    });
                    return;

                case 'quiqqer/payment-transaction':
                    require([
                        'package/quiqqer/payment-transactions/bin/backend/controls/windows/Transaction'
                    ], (TransactionWindow) => {
                        console.log(Target.get('data-object-hash'));

                        new TransactionWindow({
                            txid: Target.get('data-object-hash')
                        }).open();
                    });
                    return;
            }

            QUI.fireEvent('onQuiqqerErpCommentsClick', [this, Target]);
        },

        //region filter

        /**
         * shows only comments which fits to the filter
         *
         * @param {String} value
         */
        filter: function(value) {
            this.$filter = value.toString().toLowerCase();
            this.refresh();
        },

        /**
         * Clears the filter
         */
        clearFilter: function() {
            this.$filter = false;
            this.refresh();
        }

        //endregion
    });
});
