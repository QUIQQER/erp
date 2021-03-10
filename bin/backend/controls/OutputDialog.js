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

    'package/quiqqer/erp/bin/backend/controls/Comments',

    'qui/utils/Form',

    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/OutputDialog.html',
    'css!package/quiqqer/erp/bin/backend/controls/OutputDialog.css'

], function (QUI, QUIConfirm, QUISelect, QUISandbox, ERPComments, QUIFormUtils, QUIAjax, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        type   : 'package/quiqqer/erp/bin/backend/controls/OutputDialog',

        Binds: [
            '$onOpen',
            '$onOutputChange',
            '$onPrintFinish',
            '$getPreview',
            '$onChangeToEmail',
            '$onChangeToPDF',
            '$onChangeToPrint',
            '$resizeCommentsBox',
            '$onChangeMailRecipient'
        ],

        options: {
            entityId  : false,  // Clean entity ID WITHOUT prefix and suffix
            entityType: false,  // Entity type (e.g. "Invoice")

            comments: false,    // Comments as array [must be readble by package/quiqqer/erp/bin/backend/controls/Comments]

            showMarkAsSentOption: false,    // show checkbox for "Mark as sent"
            mailEditor          : true,     // shows editable subject and body for mail output

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

            this.$Output       = null;
            this.$Preview      = null;
            this.$customerMail = null;
            this.$Template     = null;

            this.$CommentsBox = null;
            this.$Form        = null;
            this.$MessagesBox = null;

            this.$mailSent = false;

            this.$Mail = {
                subject             : false,
                content             : false,
                attachedMediaFileIds: []
            };

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
                    entityId            : this.getAttribute('entityId'),
                    labelEntityId       : QUILocale.get(lg, 'controls.OutputDialog.labelEntityId'),
                    labelTemplate       : QUILocale.get(lg, 'controls.OutputDialog.labelTemplate'),
                    labelOutputType     : QUILocale.get(lg, 'controls.OutputDialog.labelOutputType'),
                    labelEmail          : QUILocale.get('quiqqer/quiqqer', 'recipient'),
                    showMarkAsSentOption: !!this.getAttribute('showMarkAsSentOption'),
                    mailEditor          : !!this.getAttribute('mailEditor'),
                    labelOpenMailEditor : QUILocale.get(lg, 'controls.OutputDialog.labelOpenMailEditor'),
                    labelMarkAsSent     : QUILocale.get(lg, 'controls.OutputDialog.labelMarkAsSent'),
                    descMarkAsSent      : QUILocale.get(lg, 'controls.OutputDialog.descMarkAsSent')
                })
            });

            Content.addClass('quiqqer-erp-outputDialog');

            self.$MessagesBox = Content.getElement('.quiqqer-erp-outputDialog-messages');

            // "To mail editor" button
            if (this.getAttribute('mailEditor')) {
                Content.getElement('.quiqqer-erp-outputDialog-openMailEditor').addEvent('click', function () {
                    require(['package/quiqqer/erp/bin/backend/controls/OutputMailEditor'], function (OutputMailEditor) {
                        new OutputMailEditor({
                            entityId  : self.getAttribute('entityId'),
                            entityType: self.getAttribute('entityType'),

                            mailSubject         : self.$Mail.subject,
                            mailContent         : self.$Mail.content,
                            attachedMediaFileIds: self.$Mail.attachedMediaFileIds,

                            events: {
                                onMailSubmit: function (MailData) {
                                    self.$Mail = MailData;
                                }
                            }
                        }).open();
                    });
                });
            }

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
                    var Template = templates[i];

                    if (Template.isSystemDefault && EntityData.hideSystemDefaultTemplate) {
                        continue;
                    }

                    new Element('option', {
                        value          : Template.id,
                        html           : Template.title,
                        'data-provider': Template.provider
                    }).inject(Form.elements.template);

                    if (!Selected && Template.isDefault) {
                        Selected = Template;
                    }
                }

                Form.elements.template.addEvent('change', function (event) {
                    self.$Template = {
                        id      : event.target.value,
                        provider: event.target.getElement('option[value="' + event.target.value + '"]')
                            .get('data-provider')
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
                self.$customerMail = EntityData.email;
                self.$onOutputChange();

                self.Loader.hide();

                // Load comments
                self.$CommentsBox = Content.getElement('.quiqqer-erp-outputDialog-comments');
                self.$Form        = Form;

                if (!self.getAttribute('comments') || !self.getAttribute('comments').length) {
                    self.$CommentsBox.destroy();
                    self.$CommentsBox = null;

                    return;
                }

                new ERPComments({
                    comments: self.getAttribute('comments')
                }).inject(self.$CommentsBox);

                self.$resizeCommentsBox();
            }).catch(function (e) {
                onError(e);
            });
        },

        /**
         * Render preview with selected template
         */
        $renderPreview: function () {
            var self           = this;
            var PreviewContent = this.getContent().getElement('.quiqqer-erp-outputDialog-preview');

            this.Loader.show();

            var showPreviewError = function () {
                PreviewContent.set('html', '');

                new Element('div', {
                    'class': 'quiqqer-erp-outputDialog-nopreview',
                    html   : QUILocale.get(lg, 'controls.OutputDialog.preview_error'),
                    styles : {
                        padding: 20
                    }
                }).inject(PreviewContent);
            };

            this.$getPreview().then(function (previewHtml) {
                self.Loader.hide();

                if (!previewHtml) {
                    showPreviewError();
                    return;
                }

                PreviewContent.set('html', '');

                new QUISandbox({
                    content: previewHtml,
                    styles : {
                        height : '100%',
                        padding: 20,
                        width  : '100%'
                    },
                    events : {
                        onLoad: function (Box) {
                            Box.getElm().addClass('quiqqer-erp-outputDialog-preview');
                        }
                    }
                }).inject(PreviewContent);
            }).catch(function () {
                self.Loader.hide();
                showPreviewError();
            });
        },

        /**
         * event: on submit
         */
        $onSubmit: function () {
            var self = this,
                Run  = Promise.resolve();

            this.Loader.show();

            var action = this.$Output.getValue();

            switch (action) {
                case 'print':
                    Run = this.print();
                    break;

                case 'pdf':
                    Run = this.saveAsPdf();
                    break;

                case 'email':
                    Run = this.$sendMail();
                    break;
            }

            Run.then(function () {
                var Form = self.getContent().getElement('form');

                self.fireEvent('output', [
                    QUIFormUtils.getFormData(Form),
                    self
                ]);

                var Submit = self.getButton('submit');

                switch (action) {
                    case 'print':
                        self.$addMessage(QUILocale.get(lg, 'controls.OutputDialog.msg.output_print'));
                        break;

                    case 'pdf':
                        self.$addMessage(QUILocale.get(lg, 'controls.OutputDialog.msg.output_pdf'));
                        break;

                    case 'email':
                        self.$mailSent = true;
                        Submit.disable();

                        self.$addMessage(QUILocale.get(lg, 'controls.OutputDialog.msg.mail_sent', {
                            recipient: Form.elements.recipient.value
                        }));
                        break;
                }

                self.$resizeCommentsBox();
                self.Loader.hide();
            }, function () {
                self.Loader.hide();
            });
        },

        /**
         * Add message to log
         *
         * @param {String} msg
         */
        $addMessage: function (msg) {
            var Now = new Date();

            new Element('div', {
                'class': 'quiqqer-erp-outputDialog-messages-entry box message-success',
                html   : '<b>' + Now.format('%H:%M:%S') + '</b>  ' + msg
            }).inject(this.$MessagesBox, 'top');
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
                        id   : entityId,
                        t    : self.getAttribute('entityType'),
                        oid  : self.getId(),
                        tpl  : self.$Template.id,
                        tplpr: self.$Template.provider
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
                this.Loader.hide();
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
                        id   : entityId,
                        t    : self.getAttribute('entityType'),
                        oid  : self.getId(),
                        tpl  : self.$Template.id,
                        tplpr: self.$Template.provider
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

            this.$resizeCommentsBox();
            this.getButton('submit').enable()
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

            if (this.$customerMail && Recipient.value === '') {
                Recipient.value = this.$customerMail;
            }

            Recipient.removeEvent('keyup', this.$onChangeMailRecipient);
            Recipient.addEvent('keyup', this.$onChangeMailRecipient);

            Recipient.focus();

            if (this.$mailSent) {
                Submit.disable();
            }
        },

        /**
         * If e-mail recipient changes
         *
         * @param {DocumentEvent} event
         */
        $onChangeMailRecipient: function (event) {
            var Submit = this.getButton('submit');

            Submit.enable();
            this.$mailSent = false;
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
                    'package' : 'quiqqer/erp',
                    entityId  : self.getAttribute('entityId'),
                    entityType: self.getAttribute('entityType'),
                    onError   : reject
                });
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
                });
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
                });
            });
        },

        /**
         * Get data of the entity that is outputted
         *
         * @return {Promise}
         */
        $sendMail: function () {
            var self = this,
                Form = this.getContent().getElement('form');

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_erp_ajax_output_sendMail', resolve, {
                    'package'                 : 'quiqqer/erp',
                    entityId                  : self.getAttribute('entityId'),
                    entityType                : self.getAttribute('entityType'),
                    template                  : self.$Template.id,
                    templateProvider          : self.$Template.provider,
                    mailSubject               : self.$Mail.subject,
                    mailContent               : self.$Mail.content,
                    mailAttachmentMediaFileIds: JSON.encode(self.$Mail.attachedMediaFileIds),
                    mailRecipient             : Form.elements.recipient.value,
                    onError                   : reject
                });
            });
        },

        /**
         * Resize the erp entity comments container
         */
        $resizeCommentsBox: function () {
            if (!this.$Form || !this.$CommentsBox || !this.$MessagesBox) {
                return;
            }

            var maxHeight = 685 - (710 - this.getContent().getSize().y);

            var height = this.$Form.getSize().y + this.$MessagesBox.getSize().y;
            this.$CommentsBox.setStyle('height', (maxHeight - height));
        }
    });
});
