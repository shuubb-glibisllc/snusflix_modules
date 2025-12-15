odoo.define('@bridge_skeleton/static/src/js/SkeletonKanbanRenderer', ["@bridge_skeleton/static/src/js/SkeletonKanbanRecord", "@web/views/kanban/kanban_renderer"], function (require) {
    "use strict";


    var skeletonKanbanRecord = require('@bridge_skeleton/static/src/js/SkeletonKanbanRecord');
    var KanbanRenderer = require('@web/views/kanban/kanban_renderer');

    var skeletonKanbanRenderer = KanbanRenderer.extend({
        config: _.extend({}, KanbanRenderer.prototype.config, {
            KanbanRecord: skeletonKanbanRecord,
        }),
        start: function () {
            this.$el.addClass('o_skeleton_kanban_view position-relative align-content-start flex-grow-1 flex-shrink-1');
            return this._super.apply(this, arguments);
        },
    });

    return skeletonKanbanRenderer;

});
