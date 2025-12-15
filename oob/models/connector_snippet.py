# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

from odoo import api, models, fields


class ConnectorSnippet(models.TransientModel):
    _inherit = 'connector.snippet'

    @api.model
    def _get_ecomm_extensions(self):
        """
        create channels at dynamic time for instance
        @params : self
        @return : list
        """
        ecommece_channels = super(
            ConnectorSnippet, self)._get_ecomm_extensions()
        ecommece_channels.append(('opencart', 'Opencart'))
        return ecommece_channels

    @api.model
    def create_opencart_connector_odoo_mapping(self, mapping_data, model):
        if model == 'connector.product.mapping':
            template_id = self.env['product.product'].browse(
                mapping_data.get('odoo_id')).product_tmpl_id.id
            ecomm_combination_id = self._context.get('ecomm_option_id', 0)
            if template_id and not mapping_data.get('ecomm_option_id'):
                mapping_data.update({
                    'odoo_tmpl_id': template_id,
                    'ecomm_option_id': ecomm_combination_id
                })
        return mapping_data

    @api.model
    def get_quantity(self, obj_pro, instance_id):
        """
            to get quantity of product or product template
            @params : product template obj or product obj,instance_id
            @return : quantity in hand or quantity forecasted
        """
        quantity = 0.0
        config_id = self.env['connector.instance'].browse(instance_id)
        ctx = self._context.copy() or {}
        if not 'warehouse' in ctx:
            ctx.update({
                'warehouse': config_id.warehouse_id.id
            })
        # https://github.com/odoo/odoo/blob/14.0/addons/stock/models/product.py#L119
        # qty = obj_pro.with_context(ctx)._product_available()
        qty = obj_pro.with_context(ctx)._compute_quantities_dict(obj_pro._context.get('lot_id'), obj_pro._context.get(
            'owner_id'), obj_pro._context.get('package_id'), obj_pro._context.get('from_date'), obj_pro._context.get('to_date'))
        if config_id.connector_stock_action == "qoh":
            quantity = qty[obj_pro.id]['qty_available'] - \
                qty[obj_pro.id]['outgoing_qty']
        else:
            quantity = qty[obj_pro.id]['virtual_available']
        if type(quantity) == str:
            quantity = quantity.split('.')[0]
        if type(quantity) == float:
            quantity = quantity.as_integer_ratio()[0]
        return quantity
