#!/usr/bin/env python
# -*- coding: utf-8 -*-
#################################################################################
#
#   Copyright (c) 2016-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#    See LICENSE file for full copyright and licensing details.
###############################################################################

from odoo import api, models
import json
import logging
_logger = logging.getLogger(__name__)

################## .............opencart-odoo stock.............##################


class StockMove(models.Model):
    _inherit = "stock.move"

    def fetch_stock_warehouse(self):
        """Override to check for opencart_sync locations in addition to warehouse logic"""
        ctx = dict(self._context or {})
        if 'stock_from' not in ctx:
            ecomm_cannels = dict(
                self.env['connector.snippet']._get_ecomm_extensions()).keys()
                
            # Check if there are any locations marked for OpenCart sync
            sync_locations = self.env['stock.location'].search([('opencart_sync', '=', True)])
            
            for data in self:
                odoo_product_id = data.product_id.id
                
                # Check if this stock move involves any sync-enabled locations
                locations_to_check = [data.location_id, data.location_dest_id]
                sync_location_affected = any(loc.id in sync_locations.ids for loc in locations_to_check)
                
                if sync_location_affected:
                    # If any sync location is affected, trigger OpenCart sync
                    data.check_warehouse(odoo_product_id, 0, ecomm_cannels)
                else:
                    # Fall back to original bridge skeleton logic
                    super(StockMove, self).fetch_stock_warehouse()
                    break  # Avoid double processing
                    
        return True

    def opencart_update_bulk_stock(self, mapping_data, instance):
        ctx = self._context.copy() or {}
        array = []
        route = 'BulkUpdateProductStock'
        qty_updated_data_list = []
        ctx.update({'instance_id': instance.id,
                   'warehouse': instance.warehouse_id.id})
        connection = self.env['connector.instance'].sudo(
        ).with_context(ctx)._create_opencart_connection()
        if connection['status']:
            url = connection.get('url', False)
            session_key = connection.get('session_key', False)
            opencart = connection.get('opencart', False)
            for map_obj in mapping_data:
                oc_product_id = map_obj.ecomm_id
                oc_option_id = map_obj.ecomm_option_id
                product_qty = self.env['connector.snippet'].with_context(ctx) \
                    .get_quantity(self.env['product.product'].browse(int(map_obj.odoo_id)), instance.id)

                params = {}
                params['stock'] = product_qty
                params['product_id'] = oc_product_id
                if oc_option_id != 0:
                    params['option_id'] = oc_option_id
                    params['option_qty'] = product_qty
                qty_updated_data_list.append(params)
        if qty_updated_data_list:
            stock_res = self.UpdateStockBulk(
                qty_updated_data_list, opencart, url, route, session_key)
            return stock_res
        return array

    def UpdateStockBulk(self, qty_updated_data_list, opencart, url, route, session_key):
        array = []
        try:
            para = {'product': qty_updated_data_list, 'session': session_key}
            header = {'Content-Type': 'application/json'}
            resp = opencart.get_session_key(
                url+route, json.dumps(para), False, header)
            if resp.status_code in [200, 201]:
                return [1, 'Stock Updated Successfully!!']
            else:
                return [0, 'Error! occured while updating quantities to opencart.']
        except:
            array.append([0, 'Stock Not Updated To Opencart'])
        return array

    def opencart_stock_update(self, erp_product_id, warehouse_id):
        return self.update_quantity_opencart(erp_product_id, warehouse_id)

    def update_quantity_opencart(self, erp_product_id, warehouse_id):
        ctx = self._context.copy() or {}
        product_pool = self.env['connector.product.mapping']
        check_mapping = product_pool.sudo().search(
            [('name', '=', erp_product_id)], limit=1)
        array = []
        
        # Check if there are any locations marked for OpenCart sync
        sync_locations = self.env['stock.location'].search([('opencart_sync', '=', True)])
        
        for map_obj in check_mapping:
            oc_product_id = map_obj.ecomm_id
            oc_option_id = map_obj.ecomm_option_id
            instance_id = map_obj.instance_id
            
            # Determine if we should sync: either the warehouse matches OR any sync location exists
            should_sync = False
            if sync_locations:
                # If we have sync locations, always sync regardless of warehouse
                should_sync = True
            elif instance_id and warehouse_id > 0 and warehouse_id == instance_id.warehouse_id.id:
                # Fallback to original logic if no sync locations are configured
                should_sync = True
                
            if instance_id and should_sync:
                ctx.update({'instance_id': instance_id.id})
                connection = self.env['connector.instance'].sudo(
                ).with_context(ctx)._create_opencart_connection()
                if connection['status'] and instance_id.inventory_sync == 'enable':
                    ctx['warehouse'] = instance_id.warehouse_id.id
                    product_qty = self.env['connector.snippet'].with_context(ctx) \
                        .get_quantity(self.env['product.product'].browse(erp_product_id), instance_id.id)
                    url = connection.get('url', False)
                    session_key = connection.get('session_key', False)
                    opencart = connection.get('opencart', False)
                    if url and session_key and opencart:
                        params = {}
                        route = 'UpdateProductStock'
                        params['stock'] = product_qty
                        params['product_id'] = oc_product_id
                        if oc_option_id != 0:
                            params['option_id'] = oc_option_id
                            params['option_qty'] = product_qty
                        params['session'] = session_key
                        try:
                            resp = opencart.get_session_key(url+route, params)
                            resp = resp.json()
                            key = str(resp[0])
                            status = resp[1]
                            if not status:
                                return [0, str(key)]
                            return [1, True]
                        except:
                            array.append([0, 'Stock Not Updated To Opencart'])
                    else:
                        array.append([0, 'Url Or Session Key Not Found'])
        if not array:
            array.append(
                [0, 'Error in Updating Stock, Product Id %s not mapped.' % erp_product_id])
        return array
