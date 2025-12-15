odoo.define('@bridge_skeleton/static/src/js/SkeletonKanbanView', ["@bridge_skeleton/static/src/js/SkeletonKanbanController", "@bridge_skeleton/static/src/js/SkeletonKanbanModel", "@bridge_skeleton/static/src/js/SkeletonKanbanRenderer", "@web/legacy/js/services/core", "@web/views/kanban/kanban_view", "@web/core/registry"], function (require) {
    "use strict";

    var SkeletonKanbanController = require('@bridge_skeleton/static/src/js/SkeletonKanbanController');
    var SkeletonKanbanModel = require('@bridge_skeleton/static/src/js/SkeletonKanbanModel');
    var SkeletonKanbanRenderer = require('@bridge_skeleton/static/src/js/SkeletonKanbanRenderer');
    var core = require('@web/legacy/js/services/core');
    var KanbanView = require('@web/views/kanban/kanban_view');
    var view_registry = require('@web/core/registry');

    var _lt = core._lt;

    var skeletonKanbanView = KanbanView.extend({
        config: _.extend({}, KanbanView.prototype.config, {
            Controller: SkeletonKanbanController,
            Model: SkeletonKanbanModel,
            Renderer: SkeletonKanbanRenderer,
        }),
        display_name: _lt('skeleton Kanban'),
    });

    view_registry.add('skeleton_kanban', skeletonKanbanView);

    return skeletonKanbanView;

});
