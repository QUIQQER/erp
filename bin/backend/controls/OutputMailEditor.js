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
    'qui/controls/buttons/Select',
    'qui/controls/elements/Sandbox',

    'qui/utils/Form',

    'Ajax',
    'Locale',
    'Mustache',
    'Users',

    'text!package/quiqqer/erp/bin/backend/controls/OutputMailEditor.html',
    'css!package/quiqqer/erp/bin/backend/controls/OutputMailEditor.css'

], function (QUI, QUIConfirm, QUISelect, QUISandbox, QUIFormUtils, QUIAjax, QUILocale, Mustache, Users, template) {
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

            mailSubject: false, // Mail subject that is shown initially
            mailContent: false, // Mail content that is shown initially

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
                    info            : QUILocale.get(lg, 'controls.OutputMailEditor.info')
                })
            });

            Content.addClass('quiqqer-erp-OutputMailEditor');

            this.$MailSubjectInput = Content.getElement('.quiqqer-erp-outputMailEditor-mailEditor-subject');
            this.$subject          = this.getAttribute('mailSubject');
            this.$content          = this.getAttribute('mailContent');

            this.$getMailData().then(function (MailData) {
                require(['Editors'], function (Editors) {
                    Editors.getEditor().then(function (Editor) {
                        Editor.addEvent('onLoaded', function () {
                            self.Loader.hide();
                            self.fireEvent('load', [self]);

                            Editor.resize();
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
            this.fireEvent('mailSubmit', [
                {
                    subject: this.$MailSubjectInput.value,
                    content: this.$MailContentEditor.getContent()
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
                })
            });
        }
    });
});