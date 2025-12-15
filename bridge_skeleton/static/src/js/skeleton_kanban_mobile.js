odoo.define('@bridge_skeleton/static/src/js/SkeletonKanbanMobile', ["@web/core/ui/ui_service", "@bridge_skeleton/static/src/js/SkeletonKanbanWidget", "@bridge_skeleton/static/src/js/SkeletonKanbanController"], function (require) {
    "use strict";

    // var config = require('web.config');
    var config = require('@web/core/ui/ui_service');
    var SkeletonKanbanWidget = require('@bridge_skeleton/static/src/js/SkeletonKanbanWidget');
    var SkeletonKanbanController = require('@bridge_skeleton/static/src/js/SkeletonKanbanController');

    if (!config.isSmall()) {
        return;
    }

    SkeletonKanbanWidget.include({
        template: "SkeletonKanbanWidgetMobile",
        init: function (parent, params) {
            this._super.apply(this, arguments);
            this.keepOpen = params.keepOpen || undefined;
        },
    });

    SkeletonKanbanController.include({
        init: function () {
            this._super.apply(this, arguments);
            this.openWidget = false;
        },
        _renderSkeletonKanbanWidget: function () {
            this.widgetData.keepOpen = this.openWidget;
            this.openWidget = false;
            return this._super.apply(this, arguments);
        },


    });

});
