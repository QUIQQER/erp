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

    'package/quiqqer/tooltips/bin/html5tooltips',
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
            this.$tips     = {};

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

            this.getElm().getParent('table').setStyle('margin-bottom', 0);

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
            // reset tips. if exists
            for (var i in this.$tips) {
                if (this.$tips.hasOwnProperty(i)) {
                    this.$tips[i].destroy();
                }
            }

            this.$tips = {};

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
            this.$ContainerSubject.setStyle('opacity', 0);
            this.$ContainerSubject.setStyle('display', null);

            this.$ContainerContent.set('html', '');
            this.$ContainerContent.setStyle('opacity', 0);
            this.$ContainerContent.setStyle('display', null);

            var self          = this,
                subjectLoaded = false,
                contentLoaded = false;

            var loaded = function () {
                if (!subjectLoaded && !contentLoaded) {
                    return;
                }
                
                moofx([
                    self.$ContainerSubject,
                    self.$ContainerContent
                ]).animate({
                    opacity: 1
                });
            };

            new Element('span', {
                html   : 'Betreff',
                'class': 'quiqqer-erp-email-text-settings-locale-container--label'
            }).inject(this.$ContainerSubject);

            var SubjectHelp = new Element('div', {
                'class': 'quiqqer-erp-email-text-settings-locale-container--help tooltip--help',
                html   : '?',
                styles : {
                    cursor    : 'default',
                    float     : 'right',
                    lineHeight: 30,
                    textAlign : 'center',
                    width     : 50
                },
                events : {
                    mouseenter: function (e) {
                        self.$getTooltipByNode(e).show();
                    },

                    mouseleave: function (e) {
                        self.$getTooltipByNode(e).hide();
                    }
                }
            }).inject(this.$ContainerSubject);

            var value = this.$Select.value,
                entry = this.$mailList[value];

            this.$Subject = new TranslateUpdate({
                'group'  : entry.subject[0],
                'var'    : entry.subject[1],
                'package': entry.package || '',
                events   : {
                    onLoad: function () {
                        subjectLoaded = true;
                        loaded();
                    }
                }
            }).inject(this.$ContainerSubject);

            // content
            var Parent = this.getElm().getParent('.qui-panel-content');
            var pSize  = Parent.getSize();
            var height = pSize.y - 240;

            this.$ContainerContent.setStyles({
                height: height
            });

            new Element('span', {
                html   : 'Inhalt',
                'class': 'quiqqer-erp-email-text-settings-locale-container--label'
            }).inject(this.$ContainerContent);

            var ContentHelp = new Element('div', {
                'class': 'quiqqer-erp-email-text-settings-locale-container--help tooltip--help',
                html   : '?',
                styles : {
                    cursor    : 'default',
                    float     : 'right',
                    lineHeight: 30,
                    textAlign : 'center',
                    width     : 50
                },
                events : {
                    mouseenter: function (e) {
                        self.$getTooltipByNode(e).show();
                    },

                    mouseleave: function (e) {
                        self.$getTooltipByNode(e).hide();
                    }
                }
            }).inject(this.$ContainerContent);

            this.$Content = new TranslateContent({
                'group'  : entry.content[0],
                'var'    : entry.content[1],
                'package': entry.package || '',
                styles   : {
                    height: height - 60
                },
                events   : {
                    onSaveBegin: function () {
                        self.$Panel.Loader.show();
                    },

                    onSaveEnd: function () {
                        self.$Panel.Loader.hide();
                    },

                    onLoad: function () {
                        contentLoaded = true;
                        loaded();
                    }
                }
            }).inject(this.$ContainerContent);


            // tooltips
            var options = {
                maxWidth       : "300px",
                animateFunction: "scalein",
                color          : "#daeefc",
                stickTo        : "left"
            };


            var Tip = new window.HTML5TooltipUIComponent(),
                id  = String.uniqueID();

            SubjectHelp.set('data-has-tooltip', 1);
            SubjectHelp.set('data-tooltip', id);

            options.target      = SubjectHelp;
            options.contentText = QUILocale.get(
                entry['subject.description'][0],
                entry['subject.description'][1]
            );

            Tip.set(options);
            Tip.mount();

            this.$tips[id] = Tip;


            Tip = new window.HTML5TooltipUIComponent();
            id  = String.uniqueID();

            ContentHelp.set('data-has-tooltip', 1);
            ContentHelp.set('data-tooltip', id);

            options.target      = ContentHelp;
            options.contentText = QUILocale.get(
                entry['content.description'][0],
                entry['content.description'][1]
            );

            Tip.set(options);
            Tip.mount();

            this.$tips[id] = Tip;

            this.$Panel.Loader.hide();
        },

        /**
         * Return the tooltip if exists
         *
         * @param event
         * @return {{hide: function(), show: function()}|*}
         */
        $getTooltipByNode: function (event) {
            var Target = event.target;
            var faker  = {
                show: function () {
                },
                hide: function () {
                }
            };

            if (!Target.hasClass('tooltip--help')) {
                Target = Target.getParent('.tooltip--help');
            }

            if (!Target) {
                return faker;
            }

            var id = Target.get('data-tooltip');

            if (typeof this.$tips[id] !== 'undefined') {
                return this.$tips[id];
            }

            return faker;
        }
    });
});
