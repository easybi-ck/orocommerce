define(function (require) {
    'use strict';

    var TreeManageComponent,
        $ = require('jquery'),
        _ = require('underscore'),
        __ = require('orotranslation/js/translator'),
        mediator = require('oroui/js/mediator'),
        layout = require('oroui/js/layout'),
        messenger = require('oroui/js/messenger'),
        routing = require('routing'),
        BasicTreeComponent = require('orob2bcatalog/js/app/components/basic-tree-component');

    /**
     * @export orob2bcatalog/js/app/components/tree-manage-component
     * @extends orob2bcatalog.app.components.BasicTreeComponent
     * @class orob2bcatalog.app.components.TreeManageComponent
     */
    TreeManageComponent = BasicTreeComponent.extend({
        /**
         * @property {Boolean}
         */
        updateAllowed : false,

        /**
         * @property {Boolean}
         */
        moveTriggered : false,

        /**
         * @param {Object} options
         */
        initialize: function (options) {
            TreeManageComponent.__super__.initialize.call(this, options);
            if (!this.$tree) {
                return;
            }

            this.updateAllowed = options.updateAllowed;

            this.$tree.on('select_node.jstree', _.bind(this.onSelect, this));
            this.$tree.on('move_node.jstree', _.bind(this.onMove, this));

            this._fixContainerHeight();
        },

        /**
         * @param {Object} options
         * @param {Object} config
         * @returns {Object}
         */
        customizeTreeConfig: function(options, config) {
            if (options.updateAllowed) {
                config.plugins.push('dnd');
                config['dnd'] = {
                    'copy' : false
                };
            }

            return config;
        },

        /**
         * Triggers after category selection in tree
         *
         * @param {Object} node
         * @param {Object} selected
         */
        onSelect: function(node, selected) {
            if (this.initialization || !this.updateAllowed) {
                return;
            }

            var url = routing.generate('orob2b_catalog_category_update', {id: selected.node.id});
            mediator.execute('redirectTo', {url: url});
        },
        
        /**
         * Triggers after category move
         *
         * @param {Object} e
         * @param {Object} data
         */
        onMove: function(e, data) {
            if (this.moveTriggered) {
                return;
            }

            if (data.parent == '#') {
                this.rollback(data);
                messenger.notificationFlashMessage('warning', __("orob2b.catalog.add_new_root_warning"));
                return;
            }

            var self = this;
            $.ajax({
                async: false,
                type: 'PUT',
                url: routing.generate('orob2b_catalog_category_move'),
                data: {
                    id: data.node.id,
                    parent: data.parent,
                    position: data.position
                },
                success: function (result) {
                    if (!result.status) {
                        self.rollback(data);
                        messenger.notificationFlashMessage(
                            'error',
                            __("orob2b.catalog.move_category_error", {nodeText: data.node.text})
                        );
                    }
                }
            });
        },

        /**
         * Rollback category move
         *
         * @param {Object} data
         */
        rollback: function(data) {
            this.moveTriggered = true;
            this.$tree.jstree('move_node', data.node, data.old_parent, data.old_position);
            this.moveTriggered = false;
        }
    });

    return TreeManageComponent;
});
