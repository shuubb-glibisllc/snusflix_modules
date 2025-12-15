# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

from odoo import fields, models, api


class ConnectorExtraCategory(models.Model):
    _name = "connector.extra.category"
    _order = 'id desc'
    _description = "Connector Extra Category"

    instance_id = fields.Many2one(
        'connector.instance', string='Connector Instance')
    ecommerce_channel = fields.Selection(
        related="instance_id.ecomm_type",
        string="eCommerce Channel", store=True)
    product_tmpl_id = fields.Many2one('product.template', 'Product Template', auto_join=True,
                                      index=True, ondelete="cascade", domain="[('product_variant_ids','in',[product_id])]")
    product_id = fields.Many2one('product.product', 'Product Variant', auto_join=True,
                                 index=True, ondelete="cascade", domain="[('product_tmpl_id','=',product_tmpl_id)]")
    categ_ids = fields.Many2many(
        'product.category',
        'product_categ_rel',
        'product_id',
        'categ_id',
        string='Extra Categories')

    @api.model
    def create(self, vals):
        product_tmpl_id = vals.get('product_tmpl_id')
        product_id = vals.get('product_id')
        if not product_id and product_tmpl_id:
            prod_temp_obj = self.env['product.template'].browse(
                product_tmpl_id)
            if prod_temp_obj.product_variant_count <= 1:
                vals['product_id'] = prod_temp_obj.product_variant_id.id
        if not product_tmpl_id and product_id:
            prod_obj = self.env['product.product'].browse(product_id)
            if prod_obj.product_tmpl_id.product_variant_count <= 1:
                vals['product_tmpl_id'] = prod_obj.product_tmpl_id.id
        return super(ConnectorExtraCategory, self).create(vals)
