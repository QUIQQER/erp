/**
 * @module package/quiqqer/erp/bin/backend/controls/settings/EmailTextSettings
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/settings/EmailTextSettings', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'package/quiqqer/translator/bin/controls/Update',
    'controls/lang/ContentMultiLang',
    'package/quiqqer/translator/bin/Translator',
    'package/quiqqer/translator/bin/controls/UpdateContent',
    'Locale',

    'css!package/quiqqer/erp/bin/backend/controls/settings/EmailTextSettings.css'

], function (QUI, QUIControls, QUIAjax, TranslateUpdate, ContentMultiLang,
             Translator, TranslateContent, QUILocale
) {
    "use strict";

    return new Class({

        Extends: QUIControls,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/EmailTextSettings',

        Binds: [
            '$onImport',
            '$onChange'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Elm     = null;
            this.$Input   = null;
            this.$Select  = null;
            this.$Subject = null;
            this.$Text    = null;
            this.$Panel   = null;

            this.$mailList = [];

            this.$ContainerSubject = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();
            this.$Input.removeClass('field-container-field');

            this.$Panel = QUI.Controls.getById(
                this.getElm().getParent('.qui-panel').get('data-quiid')
            );

            this.$Elm = new Element('div', {
                'class'     : 'field-container-field quiqqer-erp-email-text-settings',
                'data-quiid': this.getId(),
                'data-qui'  : this.getType()
            }).wraps(this.$Input);

            this.$Select = new Element('select', {
                disabled: true,
                styles  : {
                    width: '100%'
                }
            }).inject(this.$Elm);

            this.$Select.addEvent('change', this.$onChange);

            this.$ContainerSubject = new Element('div', {
                'class': 'quiqqer-erp-email-text-settings-locale-container',
                styles : {
                    display: 'none'
                }
            }).inject(this.$Elm);

            this.$ContainerContent = new Element('div', {
                'class': 'quiqqer-erp-email-text-settings-locale-container',
                styles : {
                    display: 'none'
                }
            }).inject(this.$Elm);


            this.$getProvider().then(function (mailList) {
                self.$mailList = mailList;
                self.$Select.set('html', '');

                new Element('option', {
                    value: '',
                    html : ''
                }).inject(self.$Select);

                for (var i = 0, len = mailList.length; i < len; i++) {
                    new Element('option', {
                        value: i,
                        html : mailList[i].title
                    }).inject(self.$Select);
                }

                if (len) {
                    self.$Select.set('disabled', false);
                }
            });
            /*
            this.$Subject = '';
            this.$Text    = '';
            */

            console.log(this.$Elm);
        },

        /**
         * fetch all mail text entries
         *
         * @return {Promise}
         */
        $getProvider: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_settings_mail_getMailTextProvider', resolve, {
                    'package': 'quiqqer/erp',
                    onError  : reject
                });
            });
        },

        /**
         * select change
         */
        $onChange: function () {
            if (!this.$mailList.length) {
                return;
            }

            // subject
            if (this.$Subject) {
                this.$Subject.destroy();
            }

            if (this.$Select.value === '') {
                this.$ContainerSubject.set('html', '');
                this.$ContainerSubject.setStyle('display', 'none');

                this.$ContainerContent.set('html', '');
                this.$ContainerContent.setStyle('display', 'none');

                return;
            }

            this.$Panel.Loader.show();

            this.$ContainerSubject.set('html', '');
            this.$ContainerSubject.setStyle('display', null);

            var self  = this,
                value = this.$Select.value,
                entry = this.$mailList[value];

            this.$Subject = new TranslateUpdate({
                'group'  : entry.subject[0],
                'var'    : entry.subject[1],
                'package': entry.package || ''
            }).inject(this.$ContainerSubject);

            // content
            var Parent = this.getElm().getParent('.qui-panel-content');
            var pSize  = Parent.getSize();
            var height = pSize.y - 220;


            this.$ContainerContent.set('html', '');
            this.$ContainerContent.setStyles({
                display: null,
                height : height
            });

            this.$Content = new TranslateContent({
                'group'  : entry.content[0],
                'var'    : entry.content[1],
                'package': entry.package || '',
                styles   : {
                    height: height - 20
                },
                events   : {
                    onSaveBegin: function () {
                        self.$Panel.Loader.show();
                    },

                    onSaveEnd: function () {
                        self.$Panel.Loader.hide();
                    }
                }
            }).inject(this.$ContainerContent);

            this.$Panel.Loader.hide();
        }
    });
});
