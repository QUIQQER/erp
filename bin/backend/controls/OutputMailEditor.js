/**
 * Edit the e-mail subject and content of an Output document
 *
 * @module package/quiqqer/erp/bin/backend/controls/OutputMailEditor
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onMailSubmit [MailData, this] - Fires if the user submits the mail data
 */
define('package/quiqqer/erp/bin/backend/controls/OutputMailEditor', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'qui/controls/elements/Sandbox',

    'qui/utils/Form',

    'Ajax',
    'Locale',
    'Mustache',
    'Users',

    'text!package/quiqqer/erp/bin/backend/controls/OutputMailEditor.html',
    'css!package/quiqqer/erp/bin/backend/controls/OutputMailEditor.css'

], function (QUI, QUIConfirm, QUIButton, QUISandbox, QUIFormUtils, QUIAjax, QUILocale, Mustache, Users, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIConfirm,
        type   : 'package/quiqqer/erp/bin/backend/controls/OutputMailEditor',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            entityId  : false,  // Clean entity ID WITHOUT prefix and suffix
            entityType: false,  // Entity type (e.g. "Invoice")

            mailSubject         : false, // Mail subject that is shown initially
            mailContent         : false, // Mail content that is shown initially
            attachedMediaFileIds: [],   // Initially attached media files

            maxHeight: 820,
            maxWidth : 900
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon         : 'fa fa-envelope',
                title        : QUILocale.get(lg, 'controls.OutputMailEditor.title'),
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

            this.$subject = null;
            this.$content = null;

            this.$MailSubjectInput  = null;
            this.$MailContentEditor = null;
            this.$Attachments       = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self    = this,
                Content = this.getContent();

            this.Loader.show();

            Content.set({
                html: Mustache.render(template, {
                    entityId        : self.getAttribute('entityId'),
                    labelEntityId   : QUILocale.get(lg, 'controls.OutputMailEditor.labelEntityId'),
                    labelMailSubject: QUILocale.get(lg, 'controls.OutputMailEditor.labelMailSubject'),
                    labelMailContent: QUILocale.get(lg, 'controls.OutputMailEditor.labelMailContent'),
                    info            : QUILocale.get(lg, 'controls.OutputMailEditor.info'),
                    attachmentInfo  : QUILocale.get(lg, 'controls.OutputMailEditor.attachmentInfo')
                })
            });

            Content.addClass('quiqqer-erp-OutputMailEditor');

            this.$MailSubjectInput = Content.getElement('.quiqqer-erp-outputMailEditor-mailEditor-subject');
            this.$subject          = this.getAttribute('mailSubject');
            this.$content          = this.getAttribute('mailContent');

            // Add attachments btn
            var AttachmentBtn = new QUIButton({
                text     : QUILocale.get(lg, 'controls.OutputMailEditor.btn.attachments'),
                title    : QUILocale.get(lg, 'controls.OutputMailEditor.btn.attachments'),
                textimage: 'fa fa-paperclip',
                styles   : {
                    float: 'right'
                },
                events   : {
                    onClick: function (Btn) {
                        var AttachmentBox = Content.getElement('.quiqqer-erp-outputMailEditor-attachments');
                        AttachmentBox.setStyle('display', 'block');

                        self.setAttribute('maxWidth', 1200);
                        self.resize();

                        Btn.destroy();
                    }
                }
            }).inject(Content.getElement('.quiqqer-erp-outputMailEditor-btn-attachments'));

            Promise.all([
                QUI.parse(Content),
                this.$getMailData()
            ]).then(function (result) {
                var MailData = result[1];

                self.$Attachments = QUI.Controls.getById(
                    Content.getElement('input[name="attachments"]').get('data-quiid')
                );

                self.$Attachments.getElm().setStyle('height', 610);

                require(['Editors'], function (Editors) {
                    Editors.getEditor().then(function (Editor) {
                        Editor.addEvent('onLoaded', function () {
                            self.Loader.hide();
                            self.fireEvent('load', [self]);

                            Editor.resize();

                            // Add previously selected media items
                            if (self.getAttribute('attachedMediaFileIds')) {
                                var mediaIds = self.getAttribute('attachedMediaFileIds');

                                if (mediaIds.length) {
                                    for (var i = 0, len = mediaIds.length; i < len; i++) {
                                        self.$Attachments.addItem(mediaIds[i]);
                                    }

                                    AttachmentBtn.click();
                                }
                            }
                        });

                        Editor.inject(
                            Content.getElement('.quiqqer-erp-outputMailEditor-mailEditor-content')
                        );

                        self.$MailContentEditor = Editor;

                        if (self.$subject) {
                            self.$MailSubjectInput.value = self.$subject;
                        } else {
                            self.$MailSubjectInput.value = MailData.subject;
                        }

                        if (self.$content) {
                            self.$MailContentEditor.setContent(self.$content);
                        } else {
                            self.$MailContentEditor.setContent(MailData.content);
                        }
                    });
                });
            });
        },

        /**
         * Event: onSubmit
         */
        $onSubmit: function () {
            var mediaIds = [];

            if (this.$Attachments.getValue()) {
                mediaIds = this.$Attachments.getValue().split(',');
            }

            this.fireEvent('mailSubmit', [
                {
                    subject             : this.$MailSubjectInput.value,
                    content             : this.$MailContentEditor.getContent(),
                    attachedMediaFileIds: mediaIds
                },
                this
            ]);

            this.close();
        },

        /**
         * Get data of the entity that is outputted
         *
         * @return {Promise}
         */
        $getMailData: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_output_getMailData', resolve, {
                    'package' : 'quiqqer/erp',
                    entityId  : self.getAttribute('entityId'),
                    entityType: self.getAttribute('entityType'),
                    onError   : reject
                });
            });
        }
    });
});