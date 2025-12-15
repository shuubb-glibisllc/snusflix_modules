/** @odoo-module **/

/* Copyright (c) 2018-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>) */
/* See LICENSE file for full copyright and licensing details. */

import { registry } from "@web/core/registry";
import { useService } from "@web/core/utils/hooks";
import { Layout } from "@web/search/layout";
import { Component, onWillStart, onMounted } from "@odoo/owl";
import { loadJS } from "@web/core/assets";
import { rpc } from "@web/core/network/rpc";


class MobDashboard extends Component {
    setup() {
        super.setup();
        loadJS('/bridge_skeleton/static/src/js/chart.js');
        this.action = useService("action");
        // this._rpc = useService("rpc");
        this.orm = useService("orm");

        onWillStart(this.willStart);

        onMounted(() => {
            var self = this;
            this.canvases = {};
            var instance = self._loadInstanceData();
            instance.then(function (data) {
                var instance_id = data.instance_detail[0];
                self._updateDashboardData(data);
                self._loadCharts(instance_id);
            })
        });
    }

    async willStart() {
        var proms = [this._renderTopStatus()];
        return Promise.all(proms);
    }

    _updateDashboardData(data) {
        if (!data) {
            return;
        }
        this._updateBridgeData(data);
        var current_instance = data.instance_detail[0];
        var allInstances = data.all_instances;
        var index;
        let select = document.getElementById('ecomm-instance');
        if (!select) {
            return;
        }
        for (index = 0; index < allInstances.length; index++) {
            var ecom_instance = allInstances[index];
            var opt = document.createElement('option');
            opt.value = ecom_instance[0];
            opt.innerHTML = ecom_instance[1];
            select.appendChild(opt);
            if (current_instance == ecom_instance[0]) {
                var selected = index;
            }
        }
        select.selectedIndex = selected;
    }

    _loadInstanceData() {
        var self = this;
        return rpc(
            '/bridge_skeleton/infos', {}
        );
    }

    _updateBridgeData(data) {
        // updating links and bridge name //
        if (data.bridge_name) {
            var lowerName = data.bridge_name.toLowerCase();
            var bridgename = data.bridge_name;
            var imglink = data.img_link;
        } else {
            var bridgename = 'ECOMMERCE';
            var imglink = "/bridge_skeleton/static/description/icon.png";
        }
        document.querySelector('#bridge-name').textContent = bridgename;
        document.querySelector('#bridge-short-form').textContent = data.bridge_short_form;
        document.querySelector('#bridge-lowercase1').textContent = lowerName;
        document.querySelector('#bridge-lowercase2').textContent = lowerName;
        document.getElementById('bridge-extension').setAttribute('href', data.extension);
        document.getElementById('user-guide').setAttribute('href', data.user_guide);
        document.getElementById('rate-review').setAttribute('href', data.rate_review);
        document.getElementById('bridge-icon').setAttribute('src', imglink);
        let sbadge = document.getElementById('id-badge-success');
        let fbadge = document.getElementById('id-badge-failure');
        if (data.status) {
            sbadge.style.display = "inline";
            fbadge.style.display = "none";
        } else {
            sbadge.style.display = "none";
            fbadge.style.display = "inline";
        }
    }

    _setCurrentInstance(instance_id) {
        return rpc('/bridge_skeleton/skeleton_instance_set',
            {
                params: {
                    instance_id: instance_id,
                }
            })
    }

    _openConnectionForm(ev) {
        let currentEle = ev.currentTarget;
        let method = currentEle.getAttribute('method');
        var self = this;
        this.orm.call(
            "connector.dashboard",
            method,
            [],
            {}).then(function (data) {
                return self.action.doAction({
                    type: data.type,
                    name: data.name,
                    res_model: data.res_model,
                    res_id: data.res_id,
                    views: data.views,
                    target: data.target,
                    context: data.context,
                    domain: data.domain
                });
            });
    }

    _openBulkSync(ev) {
        var self = this;
        this.orm.call(
            "connector.dashboard",
            'open_bulk_synchronization',
            [],
            {}).then(function (data) {
                return self.action.doAction({
                    type: data.type,
                    name: data.name,
                    res_model: data.res_model,
                    views: data.views,
                    target: 'current',
                });
            });
    }

    async _renderTopStatus() {
        this.top_status = await this._fetch_chart_data({});
    }

    _loadCharts(instance_id) {
        var self = this;
        const canvases = document.querySelectorAll('canvas');
        canvases.forEach(function (canvas) {
            const kwAction = canvas.dataset.kw_action; // Get the dataset attribute
            self.canvases[kwAction] = {
                'kw_action': kwAction,
                'instance_id': instance_id
            };
            self._renderCart(canvas);
        });
    }

    async _renderCart(chart) {
        let ctx = chart.getContext('2d');
        let canvas = this.canvases[chart.dataset.kw_action];
        if (canvas.hasOwnProperty('chart')) {
            canvas.chart.destroy();
            delete canvas['chart'];
        }
        let info = await this._fetch_chart_data(canvas);
        if (info['chart']) {
            var dashboardlabels = info['chart']['data']['datasets'][0]['label'];
            if (dashboardlabels == 'Product' || dashboardlabels == 'Order' || dashboardlabels == 'Partner' || dashboardlabels == 'Category') {
                document.querySelector('#dynamic-data').textContent = info['chart']['data']['datasets'][0]['label'];
            } else if (dashboardlabels == '#Top 5 Product' || dashboardlabels == '#Top 5 Sale Order' || dashboardlabels == '#Top 5 Customer') {
                document.querySelector('#top5data').textContent = info['chart']['data']['datasets'][0]['label'];
            } else {
                if (dashboardlabels != 'Product' || dashboardlabels != 'Order' || dashboardlabels != 'Partner' || dashboardlabels != 'Category') {

                } else {
                    document.querySelector('#top5data').textContent = '#Top 5 Product';
                    document.querySelector('#dynamic-data').textContent = 'Product';
                }
            }
        } else {
            document.querySelector('#top5data').textContent = '#Top 5';
            document.querySelector('#dynamic-data').textContent = 'Product';
        }
        canvas['chart'] = new Chart(ctx, info.chart);
    }

    _fetch_chart_data(params) {
        return rpc(
            '/backend/bridge_skeleton_dashboard',
            { params: params }
        )
    }

    onChangeFilter(ev) {
        let currentEle = ev.currentTarget;
        let dropdownButton = currentEle.closest('.btn-group').querySelector('.dropdown-toggle');
        let canvas = currentEle.closest('.canvas-container').querySelector('canvas');
        this.canvases[canvas.dataset.kw_action][dropdownButton.dataset.type] = currentEle.dataset.value;
        dropdownButton.textContent = currentEle.textContent;
        this._renderCart(canvas);
    }

    onChangeInstance(ev) {
        var self = this;
        let currentEle = ev.currentTarget;
        var current_instance_id = parseInt(currentEle.value);
        for (const [action, chart] of Object.entries(this.canvases)) {
            chart.chart.destroy();
            delete chart['chart'];
        };
        delete this.canvases;
        this.canvases = {};
        this._loadCharts(current_instance_id);
        var current_inst = this._setCurrentInstance(current_instance_id);
        current_inst.then(function (data) {
            console.log('current instance updated...');
            var instance_data = self._loadInstanceData();
            instance_data.then(function (idata) {
                self._updateBridgeData(idata);
            });
        });
    }

}
MobDashboard.template = "bridge_skeleton.admin_dashboard";
MobDashboard.components = { Layout };

registry.category("actions").add("admin_dashboard", MobDashboard);
