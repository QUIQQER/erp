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

    'Mustache',
    'Ajax',

    'text!package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates.html'

], function (QUI, QUILoader, QUIControl, Mustache, QUIAjax, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/settings/OutputTemplates',

        Binds: [
            '$onImport',
            '$getTemplates'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * @event: on import
         */
        $onImport: function () {
            var self  = this,
                Input = this.getElm();

            Input.type = 'hidden';

            var Content = new Element('div', {
                'class': 'quiqqer-erp-settings-output-templates'
            }).inject(Input, 'before');

            this.Loader.inject(Content);
            this.Loader.show();

            this.$getTemplates().then(function (templates) {
                self.Loader.hide();

                var Templates = {};

                // Parse templates by entity type
                for (var i = 0, len = templates.length; i < len; i++) {
                    var Template = templates[i];

                    if (!(Template.entityType in Templates)) {
                        Templates[Template.entityType] = {
                            entityTypeTitle: Template.entityTypeTitle,
                            outputTemplates: []
                        };
                    }

                    Templates[Template.entityType].outputTemplates.push(Template);
                }

                var renderTemplates = [];

                for (var entityType in Templates) {
                    renderTemplates.push({
                        title          : Templates[entityType].entityTypeTitle,
                        outputTemplates: Templates[entityType].outputTemplates
                    });
                }

                Content.set('html', Mustache.render(template, {
                    templates: renderTemplates
                }));

                console.log(renderTemplates);
            });
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
