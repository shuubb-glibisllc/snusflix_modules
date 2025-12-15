# -*- coding: utf-8 -*-
#################################################################################
#
#   Copyright (c) 2016-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#    See LICENSE file for full copyright and licensing details.
#################################################################################

from odoo import api, models
import json


class ConnectorSnippet(models.TransientModel):
    _inherit = 'connector.snippet'

    @api.model
    def opencart_after_order_invoice(self, connection, ecommerce_reference, id_order):
        return self.update_order_status_opencart(connection, id_order, 'paid')

    @api.model
    def opencart_after_order_cancel(self, connection, ecommerce_reference, id_order):
        return self.update_order_status_opencart(connection, id_order, 'cancel')

    @api.model
    def opencart_after_order_shipment(self, connection, ecommerce_reference, id_order):
        return self.update_order_status_opencart(connection, id_order, 'delivered')

    def update_order_status_opencart(self, connection, id_order, id_order_state):
        status = 'no'
        text = 'Status Successfully Updated'
        route = 'UpdateOrderStatus'
        url = connection.get('url', False)
        opencart = connection.get('opencart', False)
        session_key = connection.get('session_key', False)
        if url and opencart and session_key:
            data = {}
            data['order_id'] = id_order
            data['session'] = session_key
            data['order_status_id'] = id_order_state
        try:
            data = json.dumps(data)
            resp = opencart.get_session_key(url+route, data)
            resp = resp.json()
            key = str(resp[0])
            status = resp[1]
        except Exception as e:
            text = str(e)
        if status:
            status = 'yes'
        return {
            'status': status,
            'text': text
        }
