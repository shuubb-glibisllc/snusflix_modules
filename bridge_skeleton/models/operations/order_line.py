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
        
        # Enhanced debugging for order line and tax data
        _logger.info("=== ORDER LINE CREATION DEBUG ===")
        _logger.info("Incoming order_line_data: %s", order_line_data)
        
        # Detailed tax debugging
        taxes = order_line_data.get('tax_id', [])
        _logger.info("=== TAX DEBUG - OpenCart to Odoo ===")
        _logger.info("Raw tax data from OpenCart: %s (type: %s)", taxes, type(taxes))
        if taxes:
            _logger.info("✅ TAXES PASSED: OpenCart sent %d tax IDs: %s", len(taxes), taxes)
        else:
            _logger.info("❌ NO TAXES PASSED: OpenCart sent empty/no tax data")
        
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
            
            # Enhanced tax logging and validation
            product_id = order_line_data.get('product_id')
            order_id = order_line_data.get('order_id')
            odoo_product_taxes = []
            
            _logger.info("=== TAX PROCESSING DEBUG ===")
            _logger.info("Product ID: %s, Order ID: %s", product_id, order_id)
            
            # Check if product has taxes configured in Odoo for comparison
            if product_id:
                product_obj = self.env['product.product'].browse(product_id)
                odoo_product_taxes = product_obj.taxes_id.ids
                _logger.info("Product %s (%s) has taxes configured in Odoo: %s", 
                           product_obj.name, product_id, odoo_product_taxes)
            
            if taxes:
                _logger.info("🔍 VALIDATING OPENCART TAXES...")
                # Validate tax IDs exist in Odoo
                existing_taxes = self.env['account.tax'].browse(taxes).exists()
                _logger.info("Tax validation - Requested IDs: %s, Existing IDs: %s", taxes, existing_taxes.ids)
                
                if len(existing_taxes) != len(taxes):
                    missing_taxes = set(taxes) - set(existing_taxes.ids)
                    _logger.warning("❌ MISSING TAXES: OpenCart sent tax IDs that don't exist in Odoo: %s", missing_taxes)
                    # Log tax names for existing taxes
                    if existing_taxes:
                        tax_names = [f"{tax.name} (ID: {tax.id})" for tax in existing_taxes]
                        _logger.info("✅ VALID TAXES: %s", tax_names)
                else:
                    tax_names = [f"{tax.name} (ID: {tax.id})" for tax in existing_taxes]
                    _logger.info("✅ ALL TAXES VALID: %s", tax_names)
                
                order_line_data['tax_id'] = [(6, 0, taxes)]
                _logger.info("📋 FINAL TAX ASSIGNMENT: %s", order_line_data['tax_id'])
            else:
                _logger.info("🔄 NO TAXES FROM OPENCART - Using Odoo's tax system...")
                # USE ODOO'S TAX SYSTEM: Let Odoo calculate taxes based on fiscal position and product
                final_taxes = self._resolve_taxes_with_odoo_system(order_line_data, product_id, odoo_product_taxes)
                order_line_data['tax_id'] = final_taxes
                _logger.info("📋 ODOO CALCULATED TAXES: %s", final_taxes)
            
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

    def _resolve_taxes_with_odoo_system(self, order_line_data, product_id, odoo_product_taxes):
        """
        Use Odoo's native tax system with fiscal positions to determine correct taxes.
        This leverages the synchronized taxes and Odoo's built-in tax calculation logic.
        
        @param order_line_data: Order line data dictionary
        @param product_id: Odoo product ID
        @param odoo_product_taxes: Product's default taxes from Odoo
        @return: Tax assignment in Odoo format [(6, 0, [tax_ids])] or False
        """
        try:
            # Get the sale order to access customer and fiscal position
            order_id = order_line_data.get('order_id')
            if not order_id:
                _logger.warning("No order_id found in order_line_data")
                return self._fallback_to_product_taxes(product_id, odoo_product_taxes)
                
            sale_order = self.env['sale.order'].browse(order_id)
            if not sale_order:
                _logger.warning("Sale order %s not found", order_id)
                return self._fallback_to_product_taxes(product_id, odoo_product_taxes)
            
            # Get product object
            if not product_id:
                _logger.warning("No product_id provided")
                return False
                
            product = self.env['product.product'].browse(product_id)
            if not product.exists():
                _logger.warning("Product %s not found", product_id)
                return False
                
            _logger.info("🔍 Resolving taxes for product %s (%s) in order %s", 
                        product.name, product_id, sale_order.name)
            
            # Get customer information for tax calculation
            partner = sale_order.partner_id
            partner_shipping = sale_order.partner_shipping_id or partner
            partner_invoice = sale_order.partner_invoice_id or partner
            
            _logger.info("📍 Customer: %s, Shipping: %s (%s), Invoice: %s (%s)", 
                        partner.name,
                        partner_shipping.name, partner_shipping.country_id.name if partner_shipping.country_id else 'No country',
                        partner_invoice.name, partner_invoice.country_id.name if partner_invoice.country_id else 'No country')
            
            # Method 1: Use sale order's fiscal position if already set
            if sale_order.fiscal_position_id:
                _logger.info("📋 Using existing fiscal position: %s", sale_order.fiscal_position_id.name)
                fiscal_taxes = self._apply_fiscal_position(sale_order.fiscal_position_id, product)
                if fiscal_taxes:
                    return fiscal_taxes
            
            # Method 2: Determine fiscal position based on customer addresses
            fiscal_position = self._determine_fiscal_position(partner, partner_shipping, partner_invoice, sale_order.company_id)
            if fiscal_position:
                _logger.info("📋 Determined fiscal position: %s", fiscal_position.name)
                
                # Update the sale order with the fiscal position for consistency
                if not sale_order.fiscal_position_id:
                    sale_order.write({'fiscal_position_id': fiscal_position.id})
                    _logger.info("✅ Updated sale order %s with fiscal position %s", sale_order.name, fiscal_position.name)
                
                fiscal_taxes = self._apply_fiscal_position(fiscal_position, product)
                if fiscal_taxes:
                    return fiscal_taxes
            
            # Method 3: Use product's default taxes if no fiscal position applies
            if odoo_product_taxes:
                _logger.info("🔄 Using product default taxes: %s", odoo_product_taxes)
                return [(6, 0, odoo_product_taxes)]
            
            _logger.warning("🚨 No taxes found for product %s", product.name)
            return False
            
        except Exception as e:
            _logger.error("Error in Odoo tax system resolution: %s", str(e), exc_info=True)
            return self._fallback_to_product_taxes(product_id, odoo_product_taxes)
    
    def _determine_fiscal_position(self, partner, partner_shipping, partner_invoice, company):
        """
        Determine the appropriate fiscal position using Odoo's built-in logic
        """
        try:
            # Use Odoo's fiscal position determination logic
            # This considers country, country group, state, VAT, etc.
            fiscal_position = self.env['account.fiscal.position'].get_fiscal_position(
                company.id,
                partner.id,
                delivery_id=partner_shipping.id if partner_shipping != partner else None
            )
            
            if fiscal_position:
                fiscal_pos = self.env['account.fiscal.position'].browse(fiscal_position)
                _logger.info("✅ Determined fiscal position: %s (ID: %s)", fiscal_pos.name, fiscal_position)
                return fiscal_pos
            else:
                _logger.info("No fiscal position determined for customer")
                return None
                
        except Exception as e:
            _logger.error("Error determining fiscal position: %s", str(e))
            return None
    
    def _apply_fiscal_position(self, fiscal_position, product):
        """
        Apply fiscal position to product taxes using Odoo's mapping
        """
        try:
            if not product.taxes_id:
                _logger.info("Product %s has no taxes to map", product.name)
                return None
            
            # Use fiscal position to map product taxes
            mapped_taxes = fiscal_position.map_tax(product.taxes_id)
            
            if mapped_taxes:
                _logger.info("✅ Fiscal position %s mapped taxes: %s -> %s", 
                           fiscal_position.name, product.taxes_id.ids, mapped_taxes.ids)
                return [(6, 0, mapped_taxes.ids)]
            else:
                _logger.info("Fiscal position %s returned no mapped taxes for product %s", 
                           fiscal_position.name, product.name)
                return None
                
        except Exception as e:
            _logger.error("Error applying fiscal position: %s", str(e))
            return None
    
    def _fallback_to_product_taxes(self, product_id, odoo_product_taxes):
        """Fallback to product's default taxes"""
        if product_id and odoo_product_taxes:
            _logger.warning("🔄 FALLBACK: Using Odoo product taxes %s for product %s", 
                          odoo_product_taxes, product_id)
            return [(6, 0, odoo_product_taxes)]
        else:
            _logger.warning("🚨 NO TAXES: No taxes found for product %s", product_id)
            return False
