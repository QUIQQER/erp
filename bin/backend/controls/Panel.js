define('package/quiqqer/erp/bin/backend/controls/Panel', [
    'qui/QUI',
    'qui/controls/desktop/Panel',
    'utils/Panels',
    'Ajax',
    'Locale',
    'css!package/quiqqer/erp/bin/backend/controls/Panel.css'
], function (QUI, QUIPanel, PanelUtils, QUIAjax, QUILocale) {
    'use strict';

    const label = key => QUILocale.get('quiqqer/erp', 'erp.panel.navigation.' + key);
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    const element = (tag, className, text) => {
        const Node = document.createElement(tag);
        Node.className = className;
        if (text !== undefined) {
            Node.textContent = text;
        }
        return Node;
    };
    const icon = className => {
        const Icon = element('span', className || 'fa fa-angle-right');
        Icon.setAttribute('aria-hidden', 'true');
        return Icon;
    };

    return new Class({
        Extends: QUIPanel,
        Type: 'package/quiqqer/erp/bin/backend/controls/Panel',

        initialize: function (options) {
            this.parent(options);
            this.setAttributes({
                title: QUILocale.get('quiqqer/erp', 'erp.panel.title'),
                icon: 'fa fa-shopping-cart',
                scrollbars: false
            });
            this.$nodes = [];
            this.$query = '';
            this.$searchOpen = {};
            this.$disposed = false;
            const user = window.QUIQQER_USER || {};
            this.$storageKey = 'quiqqer-erp-navigation:' + (user.uuid || user.id || 'anonymous');
            this.$open = {};
            try {
                const stored = JSON.parse(QUI.Storage.get(this.$storageKey) || '{}');
                if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
                    this.$open = stored;
                }
            } catch (error) {
                // Navigation also works when browser storage is unavailable.
            }
            this.addEvents({
                onCreate: () => this.$onCreate(),
                onDestroy: () => { this.$disposed = true; }
            });
        },

        $onCreate: function () {
            const Content = this.getContent();
            Content.classList.add('quiqqer-erp-navigation');
            this.$Nav = element('nav', 'quiqqer-erp-navigation-scroll');
            this.$Nav.dataset.name = 'navigation';
            this.$Nav.setAttribute('aria-label', this.getAttribute('title'));
            this.$List = element('ul', 'quiqqer-erp-navigation-list');
            this.$Status = element('p', 'quiqqer-erp-navigation-status');
            this.$Status.setAttribute('role', 'status');
            this.$Status.hidden = true;
            this.$Retry = element('button', 'qui-button', label('retry'));
            this.$Retry.type = 'button';
            this.$Retry.hidden = true;
            this.$Retry.addEventListener('click', () => this.$load());
            this.$Nav.append(this.$List, this.$Status, this.$Retry);

            const Search = element('div', 'quiqqer-erp-navigation-search');
            Search.setAttribute('role', 'search');
            this.$Input = document.createElement('input');
            this.$Input.type = 'search';
            this.$Input.dataset.name = 'search';
            this.$Input.placeholder = label('search');
            this.$Input.setAttribute('aria-label', label('search'));
            this.$Input.addEventListener('input', () => this.$filter(this.$Input.value));
            this.$Input.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    this.$Input.value = '';
                    this.$filter('');
                    event.stopPropagation();
                }
            });
            Search.append(icon('fa fa-search'), this.$Input);
            Content.append(this.$Nav, Search);
            this.addButton({
                name: 'toggle-all',
                title: label('expandAll'),
                icon: 'fa fa-angle-double-down',
                styles: {float: 'right'},
                disabled: true,
                events: {onClick: () => this.$toggleAll()}
            });
            this.$ToggleAll = this.getButtons().find(Button => Button.getAttribute('name') === 'toggle-all');
            this.$load();
        },

        $load: function () {
            if (this.$loading || this.$disposed) {
                return;
            }
            this.$loading = true;
            this.$ToggleAll.disable();
            this.$Status.hidden = true;
            this.$Retry.hidden = true;
            this.$Input.disabled = true;
            this.Loader.show();
            QUIAjax.get('package_quiqqer_erp_ajax_panel_list', result => {
                if (this.$disposed) {
                    return;
                }
                this.$nodes = [];
                this.$List.replaceChildren();
                this.$appendItems(result.items || [], this.$List, []);
                this.$loading = false;
                this.$Input.disabled = false;
                this.Loader.hide();
                this.$filter(this.$Input.value);
            }, {
                package: 'quiqqer/erp',
                onError: () => {
                    if (this.$disposed) {
                        return;
                    }
                    this.$loading = false;
                    this.Loader.hide();
                    this.$message('loadError');
                    this.$Retry.hidden = false;
                }
            });
        },

        $appendItems: function (items, Parent, path) {
            return items.map((item, index) => {
                const text = Array.isArray(item.text)
                    ? QUILocale.get(item.text[0], item.text[1])
                    : String(item.text || '');
                const itemPath = [...path, item.name || item.require || index];
                const key = JSON.stringify(itemPath);
                const children = Array.isArray(item.items) ? item.items : [];
                const hasAction = typeof item.require === 'string' && item.require.length > 0;
                const Entry = element('li', 'quiqqer-erp-navigation-entry');
                const Row = element('div', 'quiqqer-erp-navigation-row');
                const Button = element('button', 'quiqqer-erp-navigation-link');
                Button.type = 'button';
                Button.dataset.name = 'entry';
                const Icon = icon(item.icon);
                Button.append(Icon, element('span', 'quiqqer-erp-navigation-label', text));
                Row.append(Button);
                Entry.append(Row);
                Parent.append(Entry);
                const node = {
                    key, text, item, Entry, Button, Icon, children: [],
                    opened: Object.hasOwn(this.$open, key) ? this.$open[key] === true : !!item.opened
                };
                this.$nodes.push(node);

                if (children.length) {
                    Entry.classList.add('quiqqer-erp-navigation-group');
                    const Children = element('ul', 'quiqqer-erp-navigation-list');
                    Children.id = 'erp-navigation-' + this.getId() + '-' + this.$nodes.length;
                    Entry.append(Children);
                    node.Children = Children;
                    node.Toggle = Button;
                    if (hasAction) {
                        node.Toggle = element('button', 'quiqqer-erp-navigation-toggle');
                        node.Toggle.type = 'button';
                        node.Toggle.setAttribute('aria-label', text);
                        Row.append(node.Toggle);
                    }
                    node.Toggle.append(icon('fa fa-angle-right quiqqer-erp-navigation-chevron'));
                    node.Toggle.setAttribute('aria-controls', Children.id);
                    node.Toggle.addEventListener('click', () => this.$toggle(node));
                    node.children = this.$appendItems(children, Children, itemPath);
                    this.$setOpen(node, node.opened);
                }

                if (hasAction) {
                    Button.addEventListener('click', () => this.$activate(node));
                } else if (!children.length) {
                    Button.disabled = true;
                }
                return node;
            });
        },

        $setOpen: function (node, opened) {
            node.Toggle.setAttribute('aria-expanded', String(opened));
            node.Children.hidden = !opened;
        },

        $toggle: function (node) {
            const opened = node.Toggle.getAttribute('aria-expanded') !== 'true';
            this.$setOpen(node, opened);
            if (this.$query) {
                this.$searchOpen[node.key] = opened;
                this.$updateToggleAll();
                return;
            }
            node.opened = opened;
            this.$open[node.key] = opened;
            this.$saveOpenStates();
            this.$updateToggleAll();
        },

        $saveOpenStates: function () {
            try {
                QUI.Storage.set(this.$storageKey, JSON.stringify(this.$open));
            } catch (error) {
                // Keep the current state even if it cannot be persisted.
            }
        },

        $updateToggleAll: function () {
            const groups = this.$nodes.filter(node => node.Children && !node.Entry.hidden);
            const anyOpen = groups.some(node => node.Toggle.getAttribute('aria-expanded') === 'true');
            const text = label(anyOpen ? 'collapseAll' : 'expandAll');
            this.$ToggleAll.setAttribute('title', text);
            this.$ToggleAll.setAttribute('icon', anyOpen ? 'fa fa-angle-double-up' : 'fa fa-angle-double-down');
            this.$ToggleAll.getElm().setAttribute('aria-label', text);
            this.$ToggleAll[groups.length ? 'enable' : 'disable']();
        },

        $toggleAll: function () {
            const groups = this.$nodes.filter(node => node.Children && !node.Entry.hidden);
            const opened = !groups.some(node => node.Toggle.getAttribute('aria-expanded') === 'true');
            groups.forEach(node => {
                this.$setOpen(node, opened);
                if (this.$query) {
                    this.$searchOpen[node.key] = opened;
                } else {
                    node.opened = opened;
                    this.$open[node.key] = opened;
                }
            });
            if (!this.$query) {
                this.$saveOpenStates();
            }
            this.$updateToggleAll();
        },

        $filter: function (value) {
            const query = normalize(value.trim());
            if (query !== this.$query) {
                this.$searchOpen = {};
            }
            this.$query = query;
            const visit = (node, parentMatches) => {
                const matches = parentMatches || !query || normalize(node.text).includes(query);
                const descendants = node.children.map(child => visit(child, matches));
                const visible = matches || descendants.some(Boolean);
                node.Entry.hidden = !visible;
                if (node.Children) {
                    const expanded = Object.hasOwn(this.$searchOpen, node.key)
                        ? this.$searchOpen[node.key] : true;
                    this.$setOpen(node, query ? visible && expanded : node.opened);
                }
                return visible;
            };
            const roots = this.$nodes.filter(node => node.Entry.parentNode === this.$List);
            const visible = roots.map(node => visit(node, false)).some(Boolean);
            this.$Status.hidden = visible;
            if (!visible) {
                this.$message('empty');
            }
            this.$Nav.scrollTop = 0;
            this.$updateToggleAll();
        },

        $message: function (key) {
            this.$Status.textContent = label(key);
            this.$Status.hidden = false;
        },

        $activate: async function (node) {
            if (node.busy || this.$disposed) {
                return;
            }
            node.busy = true;
            node.Button.disabled = true;
            node.Button.setAttribute('aria-busy', 'true');
            const originalIcon = node.Icon.className;
            node.Icon.className = 'fa fa-circle-o-notch fa-circle-notch fa-spin';
            try {
                const Module = await new Promise((resolve, reject) => {
                    require([node.item.require], resolve, reject);
                });
                if (this.$disposed) {
                    return;
                }
                // Providers can return a panel class or a function opening a dialog.
                if (typeOf(Module) === 'class') {
                    const Instance = new Module();
                    if (Instance instanceof QUIPanel) {
                        await PanelUtils.openPanelInTasks(Instance);
                    }
                } else if (typeof Module === 'function') {
                    await Module();
                }
            } catch (error) {
                if (!this.$disposed) {
                    this.$message('actionError');
                }
            } finally {
                node.busy = false;
                node.Button.disabled = false;
                node.Button.removeAttribute('aria-busy');
                node.Icon.className = originalIcon;
            }
        }
    });
});
