define('package/quiqqer/erp/bin/backend/controls/customerFiles/Grid', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',
    'Ajax',
    'Locale'

], function(QUI, QUIControl, Grid, QUIAjax, QUILocale) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/customerFiles/Grid',

        Binds: [
            'openCustomerFiles',
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

            this.$Grid = new Grid(Container, {
                buttons: [
                    {
                        text: 'Kundendateien',
                        title: 'Datei aus Kundendateien auswählen',
                        events: {
                            click: this.openCustomerFiles
                        }
                    }
                ],
                columnModel: [
                    {
                        header: '<span class="fa fa-envelope" title="Als Mailanhang setzen"></span>',
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

            this.$Grid.showLoader();

            this.getCustomer().then((customerHash) => {
                this.setAttribute('customerId', customerHash);
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
                    console.log(files);

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
            console.log('update');

            const gridData = this.$Grid.getData().map((entry) => {
                return {
                    hash: entry.hash,
                    options: {
                        attachToEmail: entry.mail.checked ? 1 : 0
                    }
                };
            });

            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_customerFiles_update', resolve, {
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
