/**
 * @module package/quiqqer/erp/bin/backend/controls/OutputDialog
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onOuput [FormData, this] - Fires if the user submits the popup with a chosen output format
 */
define('package/quiqqer/erp/bin/backend/controls/OutputDialog', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Select',
    'qui/controls/elements/Sandbox',

    'qui/utils/Form',

    'Ajax',
    'Locale',
    'Mustache',
    'Users',

    'text!package/quiqqer/erp/bin/backend/controls/OutputDialog.html',
    'css!package/quiqqer/erp/bin/backend/controls/OutputDialog.css'

], function (QUI, QUIConfirm, QUISelect, QUISandbox, QUIFormUtils, QUIAjax, QUILocale, Mustache, Users, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        type   : 'package/quiqqer/erp/bin/backend/controls/OutputDialog',

        Binds: [
            '$onOpen',
            '$onOutputChange',
            '$onPrintFinish',
            '$getPreview'
        ],

        options: {
            entityId  : false,  // Clean entity ID WITHOUT prefix and suffix
            entityType: false,  // Entity type (e.g. "Invoice")

            showMarkAsSentOption: false,    // show checkbox for "Mark as sent"

            maxHeight: 800,
            maxWidth : 1400
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon         : 'fa fa-print',
                title        : QUILocale.get(lg, 'controls.OutputDialog.title'),
                autoclose    : false,
                cancel_button: {
                    textimage: 'fa fa-close',
                    text     : QUILocale.get('quiqqer/system', 'close')
                }
            });

            this.$Output      = null;
            this.$Preview     = null;
            this.$cutomerMail = null;
            this.$Template    = null;

            this.addEvents({
                onOpen     : this.$onOpen,
                onSubmit   : this.$onSubmit,
                onOpenBegin: function () {
                    var winSize = QUI.getWindowSize();
                    var height  = 800;
                    var width   = 1400;

                    if (winSize.y * 0.9 < height) {
                        height = winSize.y * 0.9;
                    }

                    if (winSize.x * 0.9 < width) {
                        width = winSize.x * 0.9;
                    }

                    this.setAttribute('maxHeight', height);
                    this.setAttribute('maxWidth', width);
                }.bind(this)
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self    = this,
                Content = this.getContent();

            this.Loader.show();
            this.getContent().set('html', '');

            var onError = function (error) {
                console.error(error);

                self.close().then(function () {
                    self.destroy();
                });

                QUI.getMessageHandler().then(function (MH) {
                    if (typeof error === 'object' && typeof error.getMessage !== 'undefined') {
                        MH.addError(error.getMessage());
                        return;
                    }

                    MH.addError(error);
                });
            };

            Content.set({
                html: Mustache.render(template, {
                    entityId            : self.getAttribute('entityId'),
                    labelEntityId       : QUILocale.get(lg, 'controls.OutputDialog.labelEntityId'),
                    labelTemplate       : QUILocale.get(lg, 'controls.OutputDialog.labelTemplate'),
                    labelOutputType     : QUILocale.get(lg, 'controls.OutputDialog.labelOutputType'),
                    labelEmail          : QUILocale.get('quiqqer/quiqqer', 'recipient'),
                    showMarkAsSentOption: self.getAttribute('showMarkAsSentOption') ? true : false,
                    labelMarkAsSent     : QUILocale.get(lg, 'controls.OutputDialog.labelMarkAsSent'),
                })
            });

            Content.addClass('quiqqer-erp-outputDialog');

            this.$Output = new QUISelect({
                localeStorage: 'quiqqer-erp-output-dialog',
                name         : 'output',
                styles       : {
                    border: 'none',
                    width : '100%'
                },
                events       : {
                    onChange: self.$onOutputChange
                }
            });

            this.$Output.appendChild(
                QUILocale.get(lg, 'controls.OutputDialog.data.output.print'),
                'print',
                'fa fa-print'
            );

            this.$Output.appendChild(
                QUILocale.get(lg, 'controls.OutputDialog.data.output.pdf'),
                'pdf',
                'fa fa-file-pdf-o'
            );

            this.$Output.appendChild(
                QUILocale.get(lg, 'controls.OutputDialog.data.output.email'),
                'email',
                'fa fa-envelope-o'
            );

            this.$Output.inject(Content.getElement('.field-output'));

            Promise.all([
                this.$getTemplates(),
                this.$getEntityData()
            ]).then(function (result) {
                var templates  = result[0];
                var EntityData = result[1];

                var Form     = Content.getElement('form'),
                    Selected = false;

                if (!templates.length) {
                    new Element('option', {
                        value: '#',
                        html : QUILocale.get(lg, 'controls.OutputDialog.no_templates_found'),
                    }).inject(Form.elements.template);

                    Form.elements.template.disabled = true;

                    var PreviewContent = self.getContent().getElement('.quiqqer-erp-outputDialog-preview');

                    new Element('div', {
                        'class': 'quiqqer-erp-outputDialog-nopreview',
                        html   : QUILocale.get(lg, 'controls.OutputDialog.no_preview')
                    }).inject(PreviewContent);

                    self.$Output.disable();
                    self.getButton('submit').disable();

                    self.Loader.hide();
                    return;
                }

                for (var i = 0, len = templates.length; i < len; i++) {
                    new Element('option', {
                        value          : templates[i].id,
                        html           : templates[i].title,
                        'data-provider': templates[i].provider
                    }).inject(Form.elements.template);

                    if (!Selected) {
                        Selected = templates[i];
                    }
                }

                Form.elements.template.addEvent('change', function (event) {
                    self.$Template = {
                        id      : event.target.value,
                        provider: event.target.get('data-provider')
                    };

                    self.$renderPreview();
                });

                // Set initial template and render preview
                Form.elements.template.value = Selected.id;
                self.$Template               = {
                    id      : Selected.id,
                    provider: Selected.provider
                };

                self.$renderPreview();

                // Customer data
                self.$cutomerMail = EntityData.email;
                self.$onOutputChange();

                self.Loader.hide();
            }).catch(function (e) {
                onError(e);
            });
        },

        /**
         * Render preview with selected template
         */
        $renderPreview: function () {
            var PreviewContent = this.getContent().getElement('.quiqqer-erp-outputDialog-preview');

            this.Loader.show();

            this.$getPreview().then(function (previewHtml) {
                PreviewContent.set('html', '');

                new QUISandbox({
                    content: previewHtml,
                    styles : {
                        height : 1240,
                        padding: 20,
                        width  : 874
                    },
                    events : {
                        onLoad: function (Box) {
                            Box.getElm().addClass('quiqqer-erp-outputDialog-preview');
                        }
                    }
                }).inject(PreviewContent);
            });
        },

        /**
         * event: on submit
         */
        $onSubmit: function () {
            var self = this,
                Run  = Promise.resolve();

            this.Loader.show();

            switch (this.$Output.getValue()) {
                case 'print':
                    Run = this.print();
                    break;

                case 'pdf':
                    Run = this.saveAsPdf();
                    break;

                case 'email':
                    Run = this.sendAsEmail();
                    break;
            }

            Run.then(function () {
                var Form = self.getContent().getElement('form');

                self.fireEvent('output', [
                    QUIFormUtils.getFormData(Form),
                    self
                ]);

                self.Loader.hide();
            });
        },

        /**
         * Print the document
         *
         * @return {Promise}
         */
        print: function () {
            var self     = this,
                entityId = this.getAttribute('entityId');

            return new Promise(function (resolve) {
                var id      = 'print-document-' + entityId,
                    Content = self.getContent(),
                    Form    = Content.getElement('form');

                self.Loader.show();

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/erp/bin/output/backend/print.php?' + Object.toQueryString({
                        id : entityId,
                        t  : self.getAttribute('entityType'),
                        oid: self.getId(),
                        tpl: Form.elements.template.value
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                self.addEvent('onPrintFinish', function (self, pId) {
                    if (pId === entityId) {
                        resolve();
                    }
                });
            });
        },

        /**
         * event: on print finish
         *
         * @param {String|Number} id
         */
        $onPrintFinish: function (id) {
            this.fireEvent('printFinish', [this, id]);

            (function () {
                document.getElements('#print-document-' + id).destroy();
                this.close();
            }).delay(1000, this);
        },

        /**
         * Export the document as PDF
         *
         * @return {Promise}
         */
        saveAsPdf: function () {
            var self     = this,
                entityId = this.getAttribute('entityId');

            return new Promise(function (resolve) {
                var id      = 'download-document-' + entityId,
                    Content = self.getContent(),
                    Form    = Content.getElement('form');

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/erp/bin/output/backend/download.php?' + Object.toQueryString({
                        id : entityId,
                        t  : self.getAttribute('entityType'),
                        oid: self.getId(),
                        tpl: Form.elements.template.value
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                (function () {
                    resolve();
                }).delay(2000, this);

                (function () {
                    document.getElements('#' + id).destroy();
                }).delay(20000, this);
            });
        },

        /**
         * Send the document via e-mail
         *
         * @return {Promise}
         */
        sendAsEmail: function () {
            var self      = this,
                entityId  = this.getAttribute('entityId'),
                recipient = this.getElm().getElement('[name="recipient"]').value;

            return new Promise(function (resolve) {
                var id      = 'mail-document-' + entityId,
                    Content = self.getContent(),
                    Form    = Content.getElement('form');

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/erp/bin/output/backend/send.php?' + Object.toQueryString({
                        id       : entityId,
                        t        : self.getAttribute('entityType'),
                        oid      : self.getId(),
                        tpl      : Form.elements.template.value,
                        recipient: recipient
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                (function () {
                    document.getElements('#' + id).destroy();
                    resolve();
                }).delay(20000, this);
            });
        },

        /**
         * event : on output change
         *
         * @return {Promise}
         */
        $onOutputChange: function () {
            var Recipient = this.getElm().getElement('[name="recipient"]');

            Recipient.getParent('tr').setStyle('display', 'none');

            switch (this.$Output.getValue()) {
                case 'print':
                    this.$onChangeToPrint();
                    break;

                case 'pdf':
                    this.$onChangeToPDF();
                    break;

                case 'email':
                    this.$onChangeToEmail();
                    break;
            }
        },

        /**
         * event: on output change -> to print
         */
        $onChangeToPrint: function () {
            var Submit = this.getButton('submit');

            Submit.setAttribute('text', QUILocale.get(lg, 'controls.OutputDialog.data.output.print.btn'));
            Submit.setAttribute('textimage', 'fa fa-print');
        },

        /**
         * event: on output change -> to pdf
         */
        $onChangeToPDF: function () {
            var Submit = this.getButton('submit');

            Submit.setAttribute('text', QUILocale.get(lg, 'controls.OutputDialog.data.output.pdf.btn'));
            Submit.setAttribute('textimage', 'fa fa-file-pdf-o');
        },

        /**
         * event: on output change -> to Email
         */
        $onChangeToEmail: function () {
            var Submit    = this.getButton('submit');
            var Recipient = this.getElm().getElement('[name="recipient"]');

            Recipient.getParent('tr').setStyle('display', null);

            Submit.setAttribute('text', QUILocale.get(lg, 'controls.OutputDialog.data.output.email.btn'));
            Submit.setAttribute('textimage', 'fa fa-envelope-o');

            if (this.$cutomerMail && Recipient.value === '') {
                Recipient.value = this.$cutomerMail;
            }

            Recipient.focus();
        },

        /**
         * Get data of the entity that is outputted
         *
         * @return {Promise}
         */
        $getEntityData: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_output_getEntityData', resolve, {
                    'package': 'quiqqer/erp',
                    entityId : self.getAttribute('entityId'),
                    onError  : reject
                })
            });
        },

        /**
         * Fetch available templates based on entity type
         *
         * @return {Promise}
         */
        $getTemplates: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_output_getTemplates', resolve, {
                    'package' : 'quiqqer/erp',
                    entityType: self.getAttribute('entityType'),
                    onError   : reject
                })
            });
        },

        /**
         * Fetch available templates based on entity type
         *
         * @return {Promise}
         */
        $getPreview: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_output_getPreview', resolve, {
                    'package': 'quiqqer/erp',
                    entity   : JSON.encode({
                        id  : self.getAttribute('entityId'),
                        type: self.getAttribute('entityType')
                    }),
                    template : JSON.encode(self.$Template),
                    onError  : reject
                })
            });
        }
    });
});