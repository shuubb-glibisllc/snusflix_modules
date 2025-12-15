# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   "License URL : <https://store.webkul.com/license.html/>"
#
##########################################################################

import datetime
import calendar
import logging
import json
from odoo import http, fields, _
from odoo.http import request

_log = logging.getLogger(__name__)
TIME_LABEL_VALUE = {'%.2f' % _: 0 for _ in range(24)}
DAY_LABEL_VALUE = {d.capitalize(): 0 for d in calendar.day_name}
MONTH_LABEL_VALUE = {m.capitalize(): 0 for m in calendar.month_name if m}


class Dashborad(http.Controller):

    def _execute_query(self, query):
        result = []
        try:
            _cr = request.env.cr
            _cr.execute(query)
            result = _cr.fetchall()
        except Exception as e:
            _log.info('BRIDGE Skeleton: %e', e)
        finally:
            return result

    def _chart_dataset(self, label='Label', data=[], backgroundColor=['#7E6ED8', '#DB69C5', '#FF9979', '#20C197', '#FF75A0'], borderColor=['#FF9781', '#7E6ED8'], post={}):
        post.update({
            'label': label,
            'data': data,
            'backgroundColor': backgroundColor,
            'borderColor': '#ffffff',
            'borderWidth': 1
        })
        return [post]

    def _chart_options(self, index_axis='x', display=False):
        return {
            'scales': {
                'x': {
                    'grid': {
                        'color': "transparent",
                    }
                },
                'y': {
                    'grid': {
                        'color': "#EDEEFF",
                        'borderDash': [5, 3],
                    }
                }
            },
            'plugins': {
                'legend': {
                    'display': display
                }
            },
            'indexAxis': index_axis,
            'aspectRatio': 2,
            'responsive': True,
            'maintainAspectRatio': False,
        }

    def _default_chart_dict(self, labels=[], dataset=[], options={}, bar_type='bar'):
        options = options if options else self._chart_options()
        return {
            'chart': {
                'type': bar_type,
                'data': {
                    'labels': labels,
                    'datasets': dataset
                },
                'options': options,
            },
        }

    def _get_period_labels(self, field, period=''):
        time_period = datetime.datetime.now().date()
        if period == '7':
            label_types = dict(DAY_LABEL_VALUE)
            select_group_by = "to_char(%s, 'DAY')" % field
            time_period = time_period - \
                datetime.timedelta(days=time_period.weekday())
        elif period == '30':
            label_types = dict(MONTH_LABEL_VALUE)
            select_group_by = "to_char(%s, 'MONTH')" % field
            dd = (datetime.datetime.now().timetuple().tm_yday-1) or 1
            time_period = time_period - datetime.timedelta(days=dd)
        elif period == '365':
            label_types = dict()
            select_group_by = "EXTRACT(YEAR FROM %s)::int" % field
            time_period = ''
        else:
            label_types = dict(TIME_LABEL_VALUE)
            select_group_by = 'EXTRACT(HOUR FROM %s)' % field
        return label_types, select_group_by, time_period

    def _update_and_format_result(self, label_types, result, period=''):
        for data in result:
            if not period or period == '1':
                label_types.update({
                    '%.2f' % data[0]: data[1]
                })
            else:
                label_types.update({
                    str(data[0]).capitalize().strip(): data[1]
                })
        return label_types

    def dashboard_top_status(self, **post):
        instance_id = request.env["connector.dashboard"].get_instance()
        today = datetime.datetime.now().date()
        yesterday = today - datetime.timedelta(days=1)
        response = dict()
        # instance_id = post.get('instance_id',0)
        queries = {
            'customer': "SELECT CAST(create_date AS DATE), COUNT(*) FROM connector_partner_mapping WHERE create_date >= '%s' AND instance_id = %d GROUP BY CAST(create_date AS DATE) ORDER BY create_date LIMIT 2" % (yesterday, instance_id),
            'sale': "SELECT CAST(create_date AS DATE), COUNT(*) FROM connector_order_mapping WHERE create_date >= '%s' AND instance_id = %d GROUP BY CAST(create_date AS DATE) ORDER BY create_date LIMIT 2" % (yesterday, instance_id),
            'revenue': "SELECT CAST(crom.create_date AS DATE) AS d_create, SUM(sale.amount_total) FROM connector_order_mapping crom INNER JOIN sale_order sale ON crom.odoo_order_id=sale.id WHERE crom.create_date >= '%s' AND crom.instance_id = %d GROUP BY d_create ORDER BY d_create LIMIT 2" % (yesterday, instance_id),
            'avg_sale': "SELECT CAST(crom.create_date AS DATE) AS d_create, AVG(sale.amount_total) FROM connector_order_mapping crom INNER JOIN sale_order sale ON crom.odoo_order_id=sale.id WHERE crom.create_date >= '%s' AND crom.instance_id = %d GROUP BY d_create ORDER BY d_create LIMIT 2" % (yesterday, instance_id)
        }

        def get_rate_in_percent(result):
            if not result:
                return [0, 0]
            if len(result) == 1:
                if result[0][0] == yesterday:
                    return [0, -100]
                return [result[0][1], 100]
            y_stat = result[0][1]
            t_stat = result[1][1]
            percent = ((t_stat-y_stat)*100)/y_stat
            to_percent = round(percent, 2)
            return [t_stat, to_percent]

        for key, query in queries.items():
            result = self._execute_query(query)
            response.update({
                key: get_rate_in_percent(result)
            })
        return response

    def _filter_discounted_products(self, instance_id):
        instance_data = request.env["connector.instance"].browse(instance_id)
        return instance_data

    def _top_five_products(self, period, instance_id, discount_product, coupon_product):
        query = '''
            SELECT soline.name, SUM(soline.product_uom_qty)
            FROM sale_order_line soline
            INNER JOIN sale_order so
            ON so.id = soline.order_id
            INNER JOIN connector_order_mapping mso
            ON mso.odoo_order_id = so.id
            WHERE so.state in ('done', 'sale') %s
            AND mso.instance_id = %d
            AND soline.is_delivery = false
            AND soline.product_id NOT IN (%d, %d)
            GROUP BY soline.name
            ORDER BY SUM(soline.product_uom_qty) DESC
            LIMIT 5
        ''' % (period, instance_id, discount_product, coupon_product)
        return self._execute_query(query)

    def _top_five_sale(self, period, instance_id):
        query = '''
            SELECT so.name, so.amount_total
            FROM sale_order so
            INNER JOIN connector_order_mapping mso
            ON mso.odoo_order_id = so.id
            WHERE so.state in ('done', 'sale') %s
            AND mso.instance_id = %d
            ORDER BY so.amount_total DESC
            LIMIT 5
        ''' % (period, instance_id)
        return self._execute_query(query)

    def _top_five_customer(self, period, instance_id):
        query = '''
            SELECT resp.name, COUNT(so.id)
            FROM res_partner resp
            INNER JOIN sale_order so
            ON so.partner_id = resp.id
            INNER JOIN connector_order_mapping mso
            ON mso.odoo_order_id = so.id
            WHERE so.state in ('done', 'sale') %s
            AND mso.instance_id = %d
            GROUP BY resp.name
            ORDER BY COUNT(so.id) DESC
            LIMIT 5
        ''' % (period, instance_id)
        return self._execute_query(query)

    def dashboard_chart_mix_status(self, modal='', period=0, **post):
        params = post.get('params')
        instance_id = params.get('instance_id', 0)
        period = params.get('period')
        nmodal = params.get('nmodal') or 'bar'
        modal = params.get('modal') or ''
        stats = []
        label = _('#Top 5')
        if instance_id:
            discounted_data = self._filter_discounted_products(instance_id)
            discounted_product = discounted_data.connector_discount_product.id
            coupon_product = discounted_data.connector_coupon_product.id
            label_types, select_group_by, time_period = self._get_period_labels(
                'so.date_order', period)
            if time_period:
                time_period = "AND so.date_order >= '%s'" % time_period
            label = _('#Top 5 Product')
            # period = datetime.datetime.now().date() - datetime.timedelta(days=int(period))
            if modal == 'sale.order':
                stats = self._top_five_sale(time_period, instance_id)
                label = _('#Top 5 Sale Order')
            elif modal == 'res.partner':
                stats = self._top_five_customer(time_period, instance_id)
                label = _('#Top 5 Customer')
            else:
                stats = self._top_five_products(
                    time_period, instance_id, discounted_product, coupon_product)
        dataset = self._chart_dataset(label, [stat[1] for stat in stats])
        options = self._chart_options(index_axis='y')
        return self._default_chart_dict(
            labels=[stat[0] for stat in stats],
            dataset=dataset,
            options=options,
            bar_type=nmodal
        )

    def dashboard_chart_revenue(self, period='', **post):
        params = post.get('params')
        instance_id = params.get('instance_id', 0)
        modal = params.get('modal') or 'bar'
        period = params.get('period')
        label_types, select_group_by, time_period = self._get_period_labels(
            'so.date_order', period)
        if time_period:
            time_period = "AND so.date_order >= '%s'" % time_period
        if instance_id:
            query = '''
                SELECT %s AS res, SUM(so.amount_total)
                FROM sale_order so
                INNER JOIN connector_order_mapping mso
                ON mso.odoo_order_id = so.id
                WHERE so.state in ('done', 'sale') %s
                AND mso.instance_id = %d
                GROUP BY res
            ''' % (select_group_by, time_period, instance_id)
            result = self._execute_query(query)
            label_types = self._update_and_format_result(
                label_types, result, period)
            values = {
                'pointBorderColor': '#FF9781',
                'borderWidth': 2,
                'tension': .5,
            }
            backgroundColor = ['#7E6ED8', '#DB69C5',
                               '#FF9979', '#20C197', '#FF75A0']
            dataset = self._chart_dataset(_('#Total Revenue'),  list(
                label_types.values()), backgroundColor, '#7E6ED8', values)
        else:
            values = {
                'pointBorderColor': '#FF9781',
                'borderWidth': 2,
                'tension': .5,
            }
            backgroundColor = ['#7E6ED8', '#DB69C5',
                               '#FF9979', '#20C197', '#FF75A0']
            dataset = self._chart_dataset(_('#Total Revenue'),  list(
                label_types.values()), backgroundColor, '#7E6ED8', values)
        return self._default_chart_dict(
            labels=list(label_types.keys()),
            dataset=dataset,
            bar_type=modal
        )

    def dashboard_chart_sale(self, period='', **post):
        params = post.get('params')
        instance_id = params.get('instance_id', 0)
        modal = params.get('modal') or 'bar'
        period = params.get('period')
        result = []
        label_types, select_group_by, time_period = self._get_period_labels(
            'so.date_order', period)
        if time_period:
            time_period = "AND so.date_order >= '%s'" % time_period
        if instance_id:
            query = '''
                SELECT %s AS res, COUNT(*)
                FROM sale_order so
                INNER JOIN connector_order_mapping mso
                ON mso.odoo_order_id = so.id
                WHERE so.state in ('done', 'sale') %s
                AND mso.instance_id = %d
                GROUP BY res
            ''' % (select_group_by, time_period, instance_id)
            result = self._execute_query(query)
        label_types = self._update_and_format_result(
            label_types, result, period)
        dataset = self._chart_dataset(
            _('#Sale Order'),  list(label_types.values()))
        return self._default_chart_dict(
            labels=list(label_types.keys()),
            dataset=dataset,
            bar_type=modal
        )

    def dashboard_chart_total_orders(self, period='', **post):
        params = post.get('params')
        instance_id = params.get('instance_id', 0)
        modal = params.get('modal')
        period = params.get('period')
        dataset = []
        labels = []
        c_length = 5
        new_label_type = []
        order_states = request.env['sale.order']._fields['state'].selection
        colors = ['#7E6ED8', '#DB69C5', '#FF9979',
                  '#20C197', '#FF75A0', '#FFC962']
        l, select_group_by, time_period = self._get_period_labels(
            'so.date_order', period)

        if time_period:
            time_period = "AND so.date_order >= '%s'" % time_period

        for index, state in enumerate(order_states):
            label_types = dict(l)

            if instance_id:
                query = '''
                    SELECT %s AS res, COUNT(*)
                    FROM sale_order so
                    INNER JOIN connector_order_mapping mso
                    ON mso.odoo_order_id = so.id
                    WHERE so.state = '%s' %s
                    AND mso.instance_id = %d
                    GROUP BY res
                ''' % (select_group_by, state[0], time_period, instance_id)

                result = self._execute_query(query)
                label_types = self._update_and_format_result(
                    label_types, result, period)
                new_label_type.append(label_types)
                if not labels:
                    labels += list(label_types.keys())
                dataset.append({
                    'label': state[1],
                    'data': list(label_types.values()),
                    'backgroundColor': colors[index % c_length],
                    'borderColor': '#FFC962',
                    'tension': .12,
                    'borderWidth': 1
                })
            else:
                result = ''
                label_types = self._update_and_format_result(
                    label_types, result, period)
                if not labels:
                    labels += list(label_types.keys())
                dataset.append({
                    'label': state[1],
                    'data': list(label_types.values()),
                    'backgroundColor': colors[index % c_length],
                    'borderColor': '#FFC962',
                    'tension': .12,
                    'borderWidth': 1
                })
        options = self._chart_options(display=True)

        new_data = []
        mlabel = []
        mcolor = []
        for ndataset in dataset:
            ndata = ndataset['data']
            nlabel = ndataset['label']
            ncolor = ndataset['backgroundColor']
            if sum(ndata) > 0:
                new_data.append(sum(ndata))
            mlabel1 = [mlabel.append(nlabel) for mdata in ndata if mdata > 0]
            mcolor1 = [mcolor.append(ncolor) for mdata in ndata if mdata > 0]
        new_color = list(dict.fromkeys(mcolor))
        new_label = list(dict.fromkeys(mlabel))
        nlabels = []
        for label_type in new_label_type:
            nkey = [k for k, v in label_type.items() if v > 0]
            if nkey:
                nlabels1 = [nlabels.append(key) for key in nkey]
        new_dataset = [{
            'label': 'Orders',
            'data': new_data,
            'backgroundColor': [color for color in new_color],
            'borderColor': '#ffffff',
            'tension': .12,
            'borderWidth': 1
        }]

        if modal == 'line':
            return self._default_chart_dict(
                labels=labels,
                dataset=dataset,
                options=options,
                bar_type='line'
            )
        elif modal == 'pie':
            return self._default_chart_dict(
                labels=new_label,
                dataset=new_dataset,
                options=options,
                bar_type='pie'
            )
        elif modal == 'doughnut':
            return self._default_chart_dict(
                labels=new_label,
                dataset=new_dataset,
                options=options,
                bar_type='doughnut'
            )
        else:
            return self._default_chart_dict(
                labels=labels,
                dataset=dataset,
                options=options,
                bar_type='bar'
            )

    def dashboard_chart_top_products(self, period='', **post):
        params = post.get('params')
        instance_id = params['instance_id']
        modal = params.get('modal') or 'bar'
        period = params.get('period')
        discounted_data = self._filter_discounted_products(instance_id)
        discounted_product = discounted_data.connector_discount_product.id
        coupon_product = discounted_data.connector_coupon_product.id
        label_types, select_group_by, time_period = self._get_period_labels(
            'so.date_order', period)
        if time_period:
            time_period = "AND so.date_order >= '%s'" % time_period
        if instance_id:
            query = '''
                SELECT %s AS res, SUM(soline.product_uom_qty)
                FROM sale_order_line soline
                INNER JOIN sale_order so
                ON so.id = soline.order_id
                INNER JOIN connector_order_mapping mso
                ON mso.odoo_order_id = so.id
                WHERE so.state in ('done', 'sale') %s
                AND mso.instance_id = %d
                AND soline.is_delivery = false
                AND soline.product_id NOT IN (%d, %d)
                GROUP BY res
            ''' % (select_group_by, time_period, instance_id, discounted_product, coupon_product)
            result = self._execute_query(query)
            label_types = self._update_and_format_result(
                label_types, result, period)
            values = {
                'tension': .5,
                'pointBorderColor': '#FF9781',
                'fill': {
                    'target': 'stack',
                    'above': 'rgba(126, 110, 216, 0.3)',
                    'below': 'red'
                }
            }
            backgroundColor = ['#7E6ED8', '#DB69C5',
                               '#FF9979', '#20C197', '#FF75A0']
            dataset = self._chart_dataset(_('#Product Sale'), list(
                label_types.values()), backgroundColor, '#7E6ED8', values)
        else:
            values = {
                'tension': .5,
                'pointBorderColor': '#FF9781',
                'fill': {
                    'target': 'stack',
                    'above': 'rgba(126, 110, 216, 0.3)',
                    'below': 'red'
                }
            }
            backgroundColor = ['#7E6ED8', '#DB69C5',
                               '#FF9979', '#20C197', '#FF75A0']
            dataset = self._chart_dataset(_('#Product Sale'), list(
                label_types.values()), backgroundColor, '#7E6ED8', values)
        return self._default_chart_dict(
            labels=list(label_types.keys()),
            dataset=dataset,
            bar_type=modal
        )

    def dashboard_chart_catalogs(self, period='', **post):
        params = post.get('params')
        instance_id = params['instance_id']
        period = params.get('period')
        label_types, select_group_by, time_period = self._get_period_labels(
            'modal.create_date', period)
        modal = params.get('modal') or 'connector_product_mapping'
        nmodal = params.get('nmodal') or 'bar'
        label = modal.split('_')[1].capitalize()
        if instance_id:
            if time_period:
                time_period = "WHERE modal.create_date >= '%s'" % time_period + \
                    ' ' + "AND modal.instance_id = '%d'" % instance_id
            else:
                time_period = "WHERE modal.instance_id = '%d'" % instance_id
            query = '''
                SELECT %s AS res, COUNT(*)
                FROM %s modal %s
                GROUP BY res
            ''' % (select_group_by, modal, time_period)

            result = self._execute_query(query)
            label_types = self._update_and_format_result(
                label_types, result, period)
            dataset = self._chart_dataset(_(label), list(label_types.values()))
        else:
            result = ''
            label_types = self._update_and_format_result(
                label_types, result, period)
            dataset = self._chart_dataset(_(label), list(label_types.values()))
        return self._default_chart_dict(
            labels=list(label_types.keys()),
            dataset=dataset,
            bar_type=nmodal
        )

    @http.route('/backend/bridge_skeleton_dashboard', auth='user', type='json')
    def bridge_skeleton_dashboard(self, kw_action=False, **post):
        params = post.get('params') or ''
        if params:
            kw_action = params['kw_action']
            try:
                method_to_call = getattr(self, kw_action)
                response = method_to_call(**post)
            except Exception as e:
                _log.info(
                    "=== Bridge Skeleton Dashboard - Exception - %r ===", e)
                response = {}
            return response
        else:
            if not kw_action:
                return self.dashboard_top_status(**post)
