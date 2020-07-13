/**
 * Set default output templates for all output providers
 *
 * @module package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates', [

    'qui/QUI',
    'qui/controls/loader/Loader',
    'qui/controls/Control',

    'Locale',
    'Mustache',
    'Ajax',

    'text!package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates.html',
    'css!package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates.css'

], function (QUI, QUILoader, QUIControl, QUILocale, Mustache, QUIAjax, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates',

        Binds: [
            '$onImport',
            '$getTemplates',
            '$onTemplateSelectChange',
            '$setValue'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Loader           = new QUILoader();
            this.$Input           = null;
            this.$templateSelects = [];

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * @event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input      = this.getElm();
            this.$Input.type = 'hidden';

            var Content = new Element('div', {
                'class': 'quiqqer-erp-settings-output-templates'
            }).inject(this.$Input, 'before');

            this.Loader.inject(Content);
            this.Loader.show();

            this.$getTemplates().then(function (templates) {
                self.Loader.hide();

                var Templates = {};
                var entityType;

                // Parse templates by entity type
                for (var i = 0, len = templates.length; i < len; i++) {
                    var Template = templates[i];

                    if (!(Template.entityType in Templates)) {
                        Templates[Template.entityType] = {
                            entityTypeTitle: Template.entityTypeTitle,
                            outputTemplates: []
                        };
                    }

                    Template.isSystemDefault = Template.isSystemDefault ? 1 : 0;
                    Templates[Template.entityType].outputTemplates.push(Template);
                }

                var renderTemplates = [];

                for (entityType in Templates) {
                    renderTemplates.push({
                        title          : Templates[entityType].entityTypeTitle,
                        entityType     : entityType,
                        outputTemplates: Templates[entityType].outputTemplates
                    });
                }

                Content.set('html', Mustache.render(template, {
                    labelHideSystemDefault: QUILocale.get(lg,
                        'controls.settings.OutputTemplates.tpl.labelHideSystemDefault'
                    ),
                    templates             : renderTemplates
                }));

                self.$templateSelects = Content.getElements('.quiqqer-erp-settings-output-templates-default-select');
                self.$templateSelects.addEvent('change', self.$onTemplateSelectChange);

                var defaultCheckboxes = Content.getElements('.quiqqer-erp-settings-output-templates-hide-system-default');
                defaultCheckboxes.addEvent('change', self.$setValue);

                // Set values from setting
                if (self.$Input.value === '') {
                    return;
                }

                var Setting = JSON.decode(self.$Input.value);

                for (entityType in Setting) {
                    if (!Setting.hasOwnProperty(entityType)) {
                        continue;
                    }

                    var EntitySetting = Setting[entityType];
                    var Select        = Content.getElement('select[data-entitytype="' + entityType + '"]');

                    if (!Select) {
                        continue;
                    }

                    Select.value = EntitySetting.id + '--' + EntitySetting.provider;

                    var DefaultCheckbox = Select.getParent().getElement(
                        '.quiqqer-erp-settings-output-templates-hide-system-default input'
                    );

                    var Option = Select.getElement('option[value="' + Select.value + '"]');

                    if (!parseInt(Option.get('data-systemdefault'))) {
                        DefaultCheckbox.getParent().removeClass('quiqqer-erp-settings-output-templates__hidden');
                    }

                    DefaultCheckbox.checked = EntitySetting.hideSystemDefault;
                }
            });
        },

        /**
         * Handle change of selected template
         *
         * @param {DOMEvent} event
         */
        $onTemplateSelectChange: function (event) {
            var Select          = event.target,
                DefaultCheckbox = Select.getParent().getElement('.quiqqer-erp-settings-output-templates-hide-system-default'),
                Selected        = Select.getElement('option[value="' + Select.value + '"]');

            if (!parseInt(Selected.get('data-systemdefault'))) {
                DefaultCheckbox.removeClass('quiqqer-erp-settings-output-templates__hidden');
            } else {
                DefaultCheckbox.addClass('quiqqer-erp-settings-output-templates__hidden');
            }

            this.$setValue();
        },

        /**
         * Set default templates setting value
         */
        $setValue: function () {
            var DefaultTemplates = {};

            for (var i = 0, len = this.$templateSelects.length; i < len; i++) {
                var Select          = this.$templateSelects[i],
                    DefaultCheckbox = Select.getParent().getElement('.quiqqer-erp-settings-output-templates-hide-system-default input'),
                    value           = Select.value.split('--');

                DefaultTemplates[Select.get('data-entitytype')] = {
                    id               : value[0],
                    provider         : value[1],
                    hideSystemDefault: DefaultCheckbox.checked
                };
            }

            this.$Input.value = JSON.encode(DefaultTemplates);
        },

        /**
         * Fetch available templates for all entity types
         *
         * @return {Promise}
         */
        $getTemplates: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_output_getTemplates', resolve, {
                    'package': 'quiqqer/erp',
                    //entityType: self.getAttribute('entityType'),
                    onError  : reject
                })
            });
        },
    });
});
