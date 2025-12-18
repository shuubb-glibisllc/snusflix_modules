# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

import logging

from odoo import api, models

_logger = logging.getLogger(__name__)


def _unescape(text):
    ##
    # Replaces all encoded characters by urlib with plain utf8 string.
    #
    # @param text source text.
    # @return The plain text.
    from urllib.parse import unquote_plus
    try:
        text = unquote_plus(text)
        return text
    except Exception as e:
        return text


class WkSkeleton(models.TransientModel):
    _inherit = "wk.skeleton"

    @api.model
    def create_order_shipping_and_voucher_line(self, order_line):
        """ @params order_line: A dictionary of sale ordre line fields
                @params context: a standard odoo Dictionary with context having keyword to check origin of fumction call and identify type of line for shipping and vaoucher
                @return : A dictionary with updated values of order line"""
        ctx = dict(self._context or {})
        instance_id = ctx.get('instance_id', False)
        order_line['product_id'] = self.get_default_virtual_product_id(
            order_line, instance_id)
        if order_line.get('name', '').startswith('S'):
            order_line['is_delivery'] = True
        order_line.pop('ecommerce_channel', None)
        res = self.create_sale_order_line(order_line)
        return res

    @api.model
    def get_default_virtual_product_id(self, order_line, instance_id):
        odoo_product_id = False
        virtual_name = order_line.get('name')[:1]
        if virtual_name == 'S':
            carrier_obj = self.env['sale.order'].browse(
                order_line.get('order_id')).carrier_id
            odoo_product_id = carrier_obj.product_id.id
        elif virtual_name == 'D':
            connection_obj = self.env['connector.instance'].browse(instance_id)
            odoo_product_obj = connection_obj.connector_discount_product
            if odoo_product_obj:
                odoo_product_id = odoo_product_obj.id
            else:
                odoo_product_id = self.env['product.product'].create({
                    'sale_ok': False,
                    'name': order_line.get('name', 'Discount'),
                    'type': 'service',
                    'list_price': 0.0,
                    'description': 'Service Type product used by Magento Odoo Bridge for Discount Purposes'
                }).id
                connection_obj.connector_discount_product = odoo_product_id
        else:
            connection_obj = self.env['connector.instance'].browse(instance_id)
            odoo_product_obj = connection_obj.connector_coupon_product
            if odoo_product_obj:
                odoo_product_id = odoo_product_obj.id
            else:
                odoo_product_id = self.env['product.product'].create({
                    'sale_ok': False,
                    'name': order_line.get('name', 'Voucher'),
                    'type': 'service',
                    'list_price': 0.0,
                    'description': 'Service Type product used by Magento Odoo Bridge for Gift Voucher Purposes'
                }).id
                connection_obj.connector_coupon_product = odoo_product_id
        return odoo_product_id

    @api.model
    def create_sale_order_line(self, order_line_data):
        """Create Sale Order Lines from XML-RPC
        @param order_line_data: A List of dictionary of Sale Order line fields in which required field(s) are 'order_id', `product_uom_qty`, `price_unit`
                `product_id`: mandatory for non shipping/voucher order lines
        @return: A dictionary of Status, Order Line ID, Status Message  """
        status = True
        order_line_id = False
        statusMessage = "Order Line Successfully Created."
        
        # Log incoming order line data for debugging
        _logger.info("=== ORDER LINE CREATION DEBUG ===")
        _logger.info("Incoming order_line_data: %s", order_line_data)
        
        try:
            # To FIX:
            # Cannot call Onchange in sale order line
            productObj = self.env['product.product'].browse(
                order_line_data['product_id'])
            order_line_data.update({'product_uom': productObj.uom_id.id})
            name = order_line_data.get('name', None)
            description = order_line_data.pop('description', None)
            if description:
                order_line_data.update(name=_unescape(description))
            elif name:
                order_line_data.update(name=_unescape(name))
            else:
                order_line_data.update(
                    name=productObj.description_sale or productObj.name
                )
            
            # Enhanced tax logging
            taxes = order_line_data.get('tax_id', [])
            product_id = order_line_data.get('product_id')
            _logger.info("Raw taxes received from OpenCart: %s (type: %s)", taxes, type(taxes))
            _logger.info("Product ID: %s, Order ID: %s", product_id, order_line_data.get('order_id'))
            
            # Check if product has taxes configured in Odoo for comparison
            if product_id:
                product_obj = self.env['product.product'].browse(product_id)
                odoo_product_taxes = product_obj.taxes_id.ids
                _logger.info("Product %s (%s) has taxes configured in Odoo: %s", 
                           product_obj.name, product_id, odoo_product_taxes)
            
            if taxes:
                # Validate tax IDs exist in Odoo
                existing_taxes = self.env['account.tax'].browse(taxes).exists()
                _logger.info("Tax validation - Requested IDs: %s, Existing IDs: %s", taxes, existing_taxes.ids)
                if len(existing_taxes) != len(taxes):
                    missing_taxes = set(taxes) - set(existing_taxes.ids)
                    _logger.warning("Missing tax IDs in Odoo: %s", missing_taxes)
                
                order_line_data['tax_id'] = [(6, 0, taxes)]
                _logger.info("Final tax_id format for order line: %s", order_line_data['tax_id'])
            else:
                order_line_data['tax_id'] = False
                _logger.warning("🚨 NO TAXES PROVIDED from OpenCart for product %s - OpenCart tax resolution failed!", product_id)
            
            _logger.info("Final order_line_data before creation: %s", order_line_data)
            order_line_id = self.env['sale.order.line'].create(order_line_data)
            _logger.info("Order line created successfully with ID: %s, taxes: %s", 
                        order_line_id.id, order_line_id.tax_id.ids)
        except Exception as e:
            statusMessage = "Error in creating order Line on Odoo: %s" % str(e)
            _logger.error('## Exception create_sale_order_line for sale.order(%s) : %s'
                          % (order_line_data.get('order_id'), statusMessage))
            _logger.error('## Exception details: %s', str(e), exc_info=True)
            _logger.error('## Full order_line_data causing error: %s', order_line_data)
            status = False
        finally:
            returnDict = dict(
                order_line_id=0,
                status=status,
                status_message=statusMessage,
            )
            if order_line_id:
                returnDict.update(
                    order_line_id=order_line_id.id
                )
            return returnDict
