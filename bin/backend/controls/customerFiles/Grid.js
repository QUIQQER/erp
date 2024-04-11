define('package/quiqqer/erp/bin/backend/controls/customerFiles/Grid', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/erp/bin/backend/utils/ERPEntities',
    'controls/grid/Grid',
    'Ajax',
    'Locale'

], function(QUI, QUIControl, ERPEntityUtils, Grid, QUIAjax, QUILocale) {
    'use strict';

    const lg = 'quiqqer/erp';
    let entityTitle = '';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/customerFiles/Grid',

        Binds: [
            'openCustomerFiles',
            'removeSelectedFiles',
            '$onInject'
        ],

        options: {
            hash: false,
            customerId: false
        },

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        $onInject: function() {
            this.getElm().set('data-qui', this.getType());
            this.getElm().setStyles({
                height: '100%',
                width: '100%'
            });

            const Container = new Element('div', {
                style: {
                    height: '100%',
                    width: '100%'
                }
            }).inject(this.getElm());

            ERPEntityUtils.getEntityTitle(this.getAttribute('hash')).then((result) => {
                entityTitle = result;

                this.$Grid = new Grid(Container, {
                    multipleSelection: true,
                    buttons: [
                        {
                            text: QUILocale.get(lg, 'customer.grid.customerFiles'),
                            textimage: 'fa fa-user-o',
                            title: QUILocale.get(lg, 'customer.grid.customerFiles.title'),
                            events: {
                                click: this.openCustomerFiles
                            }
                        }, {
                            text: QUILocale.get(lg, 'customer.grid.button.remove', {
                                entity: entityTitle
                            }),
                            textimage: 'fa fa-link-slash',
                            name: 'remove',
                            disabled: true,
                            position: 'right',
                            events: {
                                click: this.removeSelectedFiles
                            }
                        }
                    ],
                    columnModel: [
                        {
                            header: '<span class="fa fa-envelope" title="' +
                                QUILocale.get(lg, 'customer.grid.mail.checkbox.title') + '"></span>',
                            dataIndex: 'mail',
                            dataType: 'node',
                            width: 50
                        }, {
                            header: QUILocale.get('quiqqer/quiqqer', 'type'),
                            dataIndex: 'icon',
                            dataType: 'node',
                            width: 40
                        }, {
                            header: QUILocale.get('quiqqer/quiqqer', 'file'),
                            dataIndex: 'basename',
                            dataType: 'string',
                            width: 300
                        }, {
                            header: QUILocale.get('quiqqer/quiqqer', 'size'),
                            dataIndex: 'filesize_formatted',
                            dataType: 'string',
                            width: 100
                        }, {
                            header: QUILocale.get('quiqqer/customer', 'window.customer.tbl.header.uploadTime'),
                            dataIndex: 'uploadTime',
                            dataType: 'string',
                            width: 100
                        }, {
                            dataIndex: 'hash',
                            dataType: 'string',
                            hidden: true
                        }
                    ]
                });

                this.$Grid.addEvents({
                    refresh: () => {
                        this.$Grid.getButtons('remove')[0].disable();
                    },
                    click: () => {
                        const selected = this.$Grid.getSelectedData();
                        const Remove = this.$Grid.getButtons('remove')[0];

                        if (selected.length) {
                            Remove.enable();
                        }
                    }
                });

                this.$Grid.showLoader();

                return this.getCustomer();
            }).then((customerHash) => {
                this.setAttribute('customerId', customerHash);
            }).then((result) => {
                return this.refresh();
            });
        },

        refresh: function() {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            this.$Grid.showLoader();

            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_customerFiles_getFiles', (files) => {
                    const data = [];

                    files.forEach((entry) => {
                        data.push({
                            hash: entry.hash,
                            basename: entry.file.basename,
                            filesize_formatted: entry.file.filesize_formatted,
                            uploadTime: entry.file.uploadTime_formatted,
                            icon: new Element('img', {
                                src: entry.file.icon,
                                styles: {
                                    margin: '5px 0'
                                }
                            }),
                            mail: new Element('input', {
                                type: 'checkbox',
                                checked: entry.options.attachToEmail
                            })
                        });
                    });

                    this.$Grid.setData({
                        data: data
                    });

                    this.getElm().getElements('[type="checkbox"]').addEvent('change', () => {
                        this.update();
                    });

                    this.$Grid.hideLoader();
                    resolve(files);
                }, {
                    'package': 'quiqqer/erp',
                    hash: this.getAttribute('hash'),
                    onError: reject
                });
            });
        },

        addFile: function(fileHash) {
            this.$Grid.showLoader();

            QUIAjax.post('package_quiqqer_erp_ajax_customerFiles_addFile', () => {
                this.refresh();
            }, {
                'package': 'quiqqer/erp',
                hash: this.getAttribute('hash'),
                fileHash: fileHash
            });
        },

        getCustomer: function() {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_customerFiles_getCustomer', resolve, {
                    'package': 'quiqqer/erp',
                    hash: this.getAttribute('hash'),
                    onError: reject
                });
            });
        },

        update: function() {
            const gridData = this.$Grid.getData().map((entry) => {
                return {
                    hash: entry.hash,
                    options: {
                        attachToEmail: entry.mail.checked ? 1 : 0
                    }
                };
            });

            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_customerFiles_update', () => {
                    QUI.getMessageHandler().then((MH) => {
                        MH.addSuccess(
                            QUILocale.get(lg, 'customer.grid.saved', {
                                entity: entityTitle
                            })
                        );
                    });

                    resolve();
                }, {
                    'package': 'quiqqer/erp',
                    hash: this.getAttribute('hash'),
                    files: JSON.encode(gridData),
                    onError: reject
                });
            });
        },

        /**
         * open customer file window
         */
        openCustomerFiles: function() {
            console.log(this.getAttribute('customerId'));

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window'
            ], (Window) => {
                new Window({
                    userId: this.getAttribute('customerId'),
                    events: {
                        onSelect: (selectedFiles, Win) => {
                            for (let File of selectedFiles) {
                                this.addFile(File.hash);
                            }

                            Win.close();
                        }
                    }
                }).open();
            });
        },

        removeSelectedFiles: function() {

            require(['qui/controls/windows/Confirm'], (QUIConfirm) => {
                new QUIConfirm({
                    maxHeight: 500,
                    maxWidth: 600,
                    information: QUILocale.get(lg, 'window.delete.customer.grid.information', {
                        entity: entityTitle
                    }),
                    text: QUILocale.get(lg, 'window.delete.customer.grid.text', {
                        entity: entityTitle
                    }),
                    title: QUILocale.get(lg, 'window.delete.customer.grid.title', {
                        entity: entityTitle
                    }),
                    texticon: 'fa fa-link-slash',
                    icon: 'fa fa-link-slash',
                    ok_button: {
                        textimage: 'fa fa-link-slash',
                        text: QUILocale.get(lg, 'window.delete.customer.grid.title', {
                            entity: entityTitle
                        })
                    },
                    events: {
                        onOpen: (Win) => {
                            const List = new Element('ul').inject(Win.getContent().getElement('.textbody'));

                            this.$Grid.getSelectedData().each((entry) => {
                                new Element('li', {
                                    html: entry.basename
                                }).inject(List);
                            });
                        },
                        onSubmit: (Win) => {
                            this.$Grid.deleteRows(this.$Grid.getSelectedIndices());
                            Win.Loader.show();

                            this.update().then(() => {
                                Win.close();
                                this.refresh();
                            });
                        }
                    }
                }).open();
            });
        },

        /**
         * Get file info
         *
         * @param {String} fileHash
         * @return {Promise}
         */
        $getFileData: function(fileHash) {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_get', resolve, {
                    'package': 'quiqqer/customer',
                    customerId: this.getAttribute('customerId'),
                    fileHash: fileHash,
                    onError: reject
                });
            });
        }
    });
});
