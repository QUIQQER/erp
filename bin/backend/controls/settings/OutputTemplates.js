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

    const lg = 'quiqqer/erp';

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

            this.Loader = new QUILoader();
            this.$Input = null;
            this.$templateSelects = [];

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * @event: on import
         */
        $onImport: function () {
            this.$Input = this.getElm();
            this.$Input.type = 'hidden';

            const Content = new Element('div', {
                'class': 'quiqqer-erp-settings-output-templates'
            }).inject(this.$Input, 'before');

            this.Loader.inject(Content);
            this.Loader.show();

            this.$getTemplates().then((templates) => {
                this.Loader.hide();

                const Templates = {};
                let entityType, Template;

                // Parse templates by entity type
                for (let i = 0, len = templates.length; i < len; i++) {
                    Template = templates[i];

                    if (!(Template.entityType in Templates)) {
                        Templates[Template.entityType] = {
                            entityTypeTitle: Template.entityTypeTitle,
                            outputTemplates: []
                        };
                    }

                    Template.isSystemDefault = Template.isSystemDefault ? 1 : 0;
                    Templates[Template.entityType].outputTemplates.push(Template);
                }

                const renderTemplates = [];

                for (entityType in Templates) {
                    renderTemplates.push({
                        title          : Templates[entityType].entityTypeTitle,
                        entityType     : entityType,
                        outputTemplates: Templates[entityType].outputTemplates
                    });
                }

                Content.set('html', Mustache.render(template, {
                    labelHideSystemDefault: QUILocale.get(lg, 'controls.settings.OutputTemplates.tpl.labelHideSystemDefault'),
                    templates             : renderTemplates
                }));

                this.$templateSelects = Content.getElements('.quiqqer-erp-settings-output-templates-default-select');
                this.$templateSelects.addEvent('change', this.$onTemplateSelectChange);

                const defaultCheckboxes = Content.getElements('.quiqqer-erp-settings-output-templates-hide-system-default');
                defaultCheckboxes.addEvent('change', this.$setValue);

                // Set values from setting
                if (this.$Input.value === '') {
                    this.$setValue();
                    return;
                }

                let EntitySetting, Select, DefaultCheckbox, Option;
                const Setting = JSON.decode(this.$Input.value);

                for (entityType in Setting) {
                    if (!Setting.hasOwnProperty(entityType)) {
                        continue;
                    }

                    EntitySetting = Setting[entityType];
                    Select = Content.getElement('select[data-entitytype="' + entityType + '"]');

                    if (!Select) {
                        continue;
                    }

                    Select.value = EntitySetting.id + '--' + EntitySetting.provider;

                    DefaultCheckbox = Select.getParent().getElement(
                        '.quiqqer-erp-settings-output-templates-hide-system-default input'
                    );

                    Option = Select.getElement('option[value="' + Select.value + '"]');

                    if (Option && !parseInt(Option.get('data-systemdefault'))) {
                        DefaultCheckbox.getParent().removeClass('quiqqer-erp-settings-output-templates__hidden');
                    }

                    DefaultCheckbox.checked = EntitySetting.hideSystemDefault;
                }

                this.$setValue();
            });
        },

        /**
         * Handle change of selected template
         *
         * @param {DOMEvent} event
         */
        $onTemplateSelectChange: function (event) {
            const Select          = event.target,
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
            const DefaultTemplates = {};

            let Select, DefaultCheckbox, value;

            for (let i = 0, len = this.$templateSelects.length; i < len; i++) {
                Select = this.$templateSelects[i];
                value = Select.value.split('--');

                DefaultCheckbox = Select.getParent().getElement(
                    '.quiqqer-erp-settings-output-templates-hide-system-default input'
                );

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
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_output_getTemplates', resolve, {
                    'package': 'quiqqer/erp',
                    //entityType: self.getAttribute('entityType'),
                    onError: reject
                });
            });
        }
    });
});
