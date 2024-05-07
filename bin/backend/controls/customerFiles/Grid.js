define('package/quiqqer/erp/bin/backend/controls/customerFiles/Grid', [

    'qui/QUI',
    'qui/controls/Control',
    'classes/request/Upload',
    'package/quiqqer/erp/bin/backend/utils/ERPEntities',
    'controls/grid/Grid',
    'Ajax',
    'Locale',

    'css!package/quiqqer/erp/bin/backend/controls/customerFiles/Grid.css'

], function(QUI, QUIControl, Upload, ERPEntityUtils, Grid, QUIAjax, QUILocale) {
    'use strict';

    const lg = 'quiqqer/erp';
    let entityTitle = '';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/customerFiles/Grid',

        Binds: [
            'openCustomerFiles',
            'uploadCustomFile',
            'removeSelectedFiles',
            '$onInject'
        ],

        options: {
            hash: false,
            customerId: false
        },

        initialize: function(options) {
            this.parent(options);

            this.$DropInfo = new Element('div');

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

            // drag drop
            this.$DropInfo = new Element('div', {
                'class': 'drag-drop-dropper',
                html: '<div class="drag-drop-dropper__inner drag-drop-dropper__child">' +
                    '      <i class="fa-solid fa-upload drag-drop-dropper__icon drag-drop-dropper__child"></i>' +
                    '  </div>'
            }).inject(this.getElm());

            this.$DropInfo.setStyle('display', 'none');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                this.getElm().addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                this.getElm().addEventListener(eventName, () => {
                    this.$DropInfo.setStyle('display', null);

                    if (!this.$DropInfo.hasClass('drag-drop-dropper--animation')) {
                        this.$DropInfo.addClass('drag-drop-dropper--animation');
                    }
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                this.getElm().addEventListener(eventName, (e) => {
                    if (e.relatedTarget && e.relatedTarget === this.$DropInfo) {
                        return;
                    }

                    if (e.relatedTarget
                        && e.relatedTarget.parentNode
                        && e.relatedTarget.getParent('.drag-drop-dropper')) {
                        return;
                    }

                    this.$DropInfo.setStyle('display', 'none');
                    this.$DropInfo.removeClass('drag-drop-dropper--animation');
                }, false);
            });

            this.getElm().addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = Array.from(dt.files);

                this.$Grid.showLoader();
                this.$uploadFiles(files);
            }, false);

            // grid
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
                            title: QUILocale.get(lg, 'customer.grid.uploadCustomerFile'),
                            icon: 'fa fa-upload',
                            events: {
                                click: this.uploadCustomFile
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
                            header: QUILocale.get('quiqqer/core', 'type'),
                            dataIndex: 'icon',
                            dataType: 'node',
                            width: 40
                        }, {
                            header: QUILocale.get('quiqqer/core', 'file'),
                            dataIndex: 'basename',
                            dataType: 'string',
                            width: 300
                        }, {
                            header: QUILocale.get('quiqqer/core', 'size'),
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
            }).then(() => {
                return this.refresh();
            });
        },

        refresh: function() {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            this.$Grid.showLoader();
            this.$Grid.getButtons('remove')[0].disable();

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

        addFiles: function(fileHashes) {
            this.$Grid.showLoader();

            return new Promise((resolve) => {
                QUIAjax.post('package_quiqqer_erp_ajax_customerFiles_addFiles', () => {
                    this.refresh();
                    resolve();
                }, {
                    'package': 'quiqqer/erp',
                    hash: this.getAttribute('hash'),
                    fileHashes: JSON.encode(fileHashes)
                });
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
         * @param files
         * @return {Promise<unknown>}
         */
        $uploadFiles: function(files) {
            return new Promise((resolve) => {
                require(['classes/request/BulkUpload'], (BulkUpload) => {
                    const Instance = new BulkUpload({
                        phpOnFinish: 'package_quiqqer_customer_ajax_backend_files_upload',
                        params: {
                            hash: this.getAttribute('hash'),
                            customerId: this.getAttribute('customerId')
                        },
                        events: {
                            onFinish: (Instance, uploadedFiles) => {
                                this.addFiles(uploadedFiles).then(() => {
                                    this.refresh();
                                    resolve();
                                });
                            }
                        }
                    });

                    if (files instanceof FileList) {
                        files = Array.from(files);
                    }
                    
                    Instance.upload(files);
                });
            });
        },

        /**
         * open customer file window
         */
        openCustomerFiles: function() {
            require([
                'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window'
            ], (Window) => {
                new Window({
                    userId: this.getAttribute('customerId'),
                    events: {
                        onSelect: (selectedFiles, Win) => {
                            // @todo refactor to add files
                            for (let File of selectedFiles) {
                                this.addFile(File.hash);
                            }

                            Win.close();
                        }
                    }
                }).open();
            });
        },

        uploadCustomFile: function() {
            const Container = new Element('div', {
                html: '<form action="" method="">' +
                    '<input type="file" name="files" value="upload" multiple />' +
                    '</form>'
            });

            Container.getElement('input').click();
            Container.getElement('input').addEvent('change', (event) => {
                const Target = event.target,
                    files = Target.files;

                if (files.length) {
                    this.$uploadFiles(files).then(() => {
                        Container.destroy();
                    });
                }
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
