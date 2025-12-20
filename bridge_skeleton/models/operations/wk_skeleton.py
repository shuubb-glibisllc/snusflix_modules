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


class WkSkeleton(models.TransientModel):
    _name = "wk.skeleton"
    _description = " Skeleton for all XML RPC imports in Odoo"

    @api.model
    def get_ecomm_href(self, getcommtype=False):
        href_list = {}
        if getcommtype == 'magento':
            href_list = {
                'user_guide': 'https://webkul.com/blog/magento-openerp-bridge/',
                'rate_review': 'https://store.webkul.com/Magento-OpenERP-Bridge.html#tabreviews',
                'extension': 'https://store.webkul.com/Magento-Extensions/ERP.html',
                'name': 'MAGENTO',
                'short_form': 'Mob',
                'img_link': '/bridge_skeleton/static/src/img/magento-logo.png'
            }
        if getcommtype == 'test2':
            href_list = {
                'user_guide': 'https://store.webkul.com/Prestashop-Openerp-Connector.html',
                'rate_review': 'https://store.webkul.com/Prestashop-Openerp-Connector.html#tabreviews',
                'extension': 'https://store.webkul.com/PrestaShop-Extensions.html',
                'name': 'PRESTASHOP',
                'short_form': 'Pob',
                'img_link': '/bridge_skeleton/static/src/img/pob-logo.png'
            }
        return href_list

    @api.model
    def delete_odoo_mappings(self, odoo_ids, odoo_model):
        status, msg = True, "Mapping successfully deleted"
        try:
            instance_id = self._context.get('instance_id')
            mapModel = 'magento.attribute.set' if odoo_model == 'set' else f'connector.{odoo_model}.mapping'
            odoo_field = 'id' if odoo_model == 'set' else 'odoo_id'
            mappings = self.env[mapModel].search(
                [(odoo_field, 'in', odoo_ids), ('instance_id', '=', instance_id)])
            if mappings:
                mappings.unlink()
        except Exception as e:
            _logger.info(
                "### Exception during odoo %s mapping delete :- %s", odoo_model, str(e))
            status = False
            msg = str(e)
        finally:
            return {
                'status': status,
                'message': msg
            }

    @api.model
    def set_extra_values(self):
        """ Add extra values"""
        return True
    # Order Status Updates

    @api.model
    def set_order_cancel(self, order_id):
        """Cancel the order in Odoo via requests from XML-RPC
                @param order_id: Odoo Order ID
                @param context: Mandatory Dictionary with key 'ecommerce' to identify the request from E-Commerce
                @return: A dictionary of status and status message of transaction"""
        ctx = dict(self._context or {})
        status = True
        status_message = "Order Successfully Cancelled."
        isVoucherInstalled = False
        try:
            saleObj = self.env['sale.order'].browse(order_id)
            status_message = "Odoo Order %s Cancelled Successfully." % (
                saleObj.name)
            if self.env['ir.module.module'].sudo().search(
                    [('name', '=', 'account_voucher')], limit=1).state == 'installed':
                isVoucherInstalled = True
                voucherModel = self.env['account.voucher']
            if saleObj.invoice_ids:
                for invoiceObj in saleObj.invoice_ids:
                    # invoiceObj.journal_id.update_posted = True
                    # if invoiceObj.state == "paid" and isVoucherInstalled:
                    #     for paymentObj in invoiceObj.payment_ids:
                    #         voucherObjs = voucherModel.search(
                    #             [('move_ids.name', '=', paymentObj.name)])
                    #         if voucherObjs:
                    #             for voucherObj in voucherObjs:
                    #                 voucherObj.journal_id.update_posted = True
                    #                 voucherObj.cancel_voucher()
                    invoiceObj.button_cancel()
            if saleObj.picking_ids:
                if 'done' in saleObj.picking_ids.mapped('state'):
                    donePickingNames = saleObj.picking_ids.filtered(
                        lambda pickingObj: pickingObj.state == 'done').mapped('name')
                    status = True
                    status_message = "Odoo Order %s Cancelled but transferred pickings can't cancelled," % (
                        saleObj.name) + " Please create return for pickings %s !!!" % (", ".join(donePickingNames))
            saleObj.with_context(disable_cancel_warning=True).action_cancel()
        except Exception as e:
            status = False
            status_message = "Odoo Order %s Not cancelled. Reason: %s" % (
                saleObj.name, str(e))
            _logger.debug('#Exception set_order_cancel for sale.order(%s) : %s' % (
                order_id, status_message))
        finally:
            return {
                'status_message': status_message,
                'status': status
            }

    @api.model
    def get_default_configuration_data(self, ecommerce_channel, instance_id):
        """@return: Return a dictionary of Sale Order keys by browsing the Configuration of Bridge Module Installed"""
        connection_obj = self.env['connector.instance'].browse(instance_id)
        sale_data = {
            'payment_term_id': connection_obj.connector_payment_term and connection_obj.connector_payment_term.id,
            'team_id': connection_obj.connector_sales_team and connection_obj.connector_sales_team.id,
            'user_id': connection_obj.connector_sales_person and connection_obj.connector_sales_person.id,
        }
        if hasattr(self, 'get_%s_configuration_data' % ecommerce_channel):
            response = getattr(
                self, 'get_%s_configuration_data' %
                ecommerce_channel)(connection_obj)
            sale_data.update(response)
        return sale_data

    @api.model
    def create_order_mapping(self, mapData):
        """Create Mapping on Odoo end for newly created order
        @param order_id: Odoo Order ID
        @context : A dictionary consisting of e-commerce Order ID"""

        self.env['connector.order.mapping'].create(mapData)
        return True

    @api.model
    def create_order(self, sale_data):
        """ Create Order on Odoo along with creating Mapping
        @param sale_data: dictionary of Odoo sale.order model fields
        @param context: Standard dictionary with 'ecommerce' key to identify the origin of request and
                                        e-commerce order ID.
        @return: A dictionary with status, order_id, and status_message"""
        ctx = dict(self._context or {})
        
        # Enhanced logging for order creation
        _logger.info("=== ORDER CREATION DEBUG ===")
        _logger.info("Incoming sale_data: %s", sale_data)
        _logger.info("Context: %s", ctx)
        
        # check sale_data for min no of keys presen or not
        order_name, order_id, status, status_message = "", False, True, "Order Successfully Created."
        ecommerce_channel = sale_data.get('ecommerce_channel')
        instance_id = ctx.get('instance_id', 0)
        ecommerce_order_id = sale_data.pop('ecommerce_order_id', 0)
        
        _logger.info("Order creation - Channel: %s, Instance: %s, EcommerceOrderID: %s", 
                    ecommerce_channel, instance_id, ecommerce_order_id)
        config_data = self.get_default_configuration_data(
            ecommerce_channel, instance_id)
        sale_data.update(config_data)

        try:
            order_obj = self.env['sale.order'].create(sale_data)
            order_id = order_obj.id
            order_name = order_obj.name
            
            # Set fiscal position based on invoice country
            self._set_fiscal_position_from_invoice_country(order_obj)
            
            # Log order customer country information for tax debugging
            if order_obj.partner_id:
                _logger.info("Order %s created for customer %s from country: %s (ID: %s)", 
                           order_name, order_obj.partner_id.name, 
                           order_obj.partner_id.country_id.name if order_obj.partner_id.country_id else 'No country',
                           order_obj.partner_id.country_id.id if order_obj.partner_id.country_id else None)
            if order_obj.partner_shipping_id and order_obj.partner_shipping_id != order_obj.partner_id:
                _logger.info("Shipping address country: %s (ID: %s)", 
                           order_obj.partner_shipping_id.country_id.name if order_obj.partner_shipping_id.country_id else 'No country',
                           order_obj.partner_shipping_id.country_id.id if order_obj.partner_shipping_id.country_id else None)
            if order_obj.fiscal_position_id:
                _logger.info("Fiscal position set: %s", order_obj.fiscal_position_id.name)
            mapping_data = {
                'ecommerce_channel': ecommerce_channel,
                'odoo_order_id': order_id,
                'ecommerce_order_id': ecommerce_order_id,
                'instance_id': instance_id,
                'name': sale_data['origin'],
            }
            self.create_order_mapping(mapping_data)
        except Exception as e:
            status_message = "Error in creating order on Odoo: %s" % str(e)
            _logger.debug('#Exception create_order : %r', status_message)
            status = False
        finally:
            return {
                'order_id': order_id,
                'order_name': order_name,
                'status_message': status_message,
                'status': status
            }

    @api.model
    def confirm_odoo_order(self, order_id):
        """ Confirms Odoo Order from E-Commerce
        @param order_id: Odoo/ERP Sale Order ID
        @return: a dictionary of True or False based on Transaction Result with status_message"""
        # REMOVED this long as python3 not supported long  # if isinstance(order_id, (int, long)):
        if isinstance(order_id, (int)):
            order_id = [order_id]
        ctx = dict(self._context or {})
        status = True
        status_message = "Order Successfully Confirmed!!!"
        try:
            saleObj = self.env['sale.order'].browse(order_id)
            saleObj.action_confirm()
        except Exception as e:
            status_message = "Error in Confirming Order on Odoo: %s" % str(e)
            _logger.debug('#Exception confirm_odoo_order for sale.order(%s) : %s' % (
                order_id, status_message))
            status = False
        finally:
            return {
                'status': status,
                'status_message': status_message
            }

    @api.model
    def _set_fiscal_position_from_invoice_country(self, sale_order):
        """
        Set fiscal position on sales order based on invoice address country from OpenCart.
        OpenCart sends partner_invoice_id which contains the billing/invoice address.
        This ensures proper tax localization based on the invoice address country.
        
        @param sale_order: sale.order record
        """
        try:
            if sale_order.fiscal_position_id:
                _logger.info("Order %s already has fiscal position: %s", 
                           sale_order.name, sale_order.fiscal_position_id.name)
                return
            
            # Get invoice address from OpenCart (partner_invoice_id contains billing address)
            partner_invoice = sale_order.partner_invoice_id
            
            if not partner_invoice:
                _logger.warning("No invoice address (partner_invoice_id) found for order %s", sale_order.name)
                return
                
            if not partner_invoice.country_id:
                _logger.warning("No country set on invoice address for order %s (partner: %s)", 
                              sale_order.name, partner_invoice.name)
                return
            
            invoice_country = partner_invoice.country_id
            _logger.info("Determining fiscal position for order %s based on INVOICE address country: %s (partner_invoice_id: %s)", 
                        sale_order.name, invoice_country.name, partner_invoice.id)
            
            # Find fiscal position specifically for the invoice country
            # Use the invoice address partner directly for fiscal position determination
            fiscal_position_id = self.env['account.fiscal.position'].get_fiscal_position(
                sale_order.company_id.id,
                partner_invoice.id  # Use invoice address partner for fiscal position lookup
            )
            
            if fiscal_position_id:
                fiscal_position = self.env['account.fiscal.position'].browse(fiscal_position_id)
                sale_order.write({'fiscal_position_id': fiscal_position_id})
                _logger.info("✅ Set fiscal position '%s' for order %s based on INVOICE country %s", 
                           fiscal_position.name, sale_order.name, invoice_country.name)
            else:
                # If no specific fiscal position found, look for one that matches the invoice country
                fiscal_positions = self.env['account.fiscal.position'].search([
                    ('country_id', '=', invoice_country.id),
                    ('company_id', '=', sale_order.company_id.id)
                ], limit=1)
                
                if fiscal_positions:
                    sale_order.write({'fiscal_position_id': fiscal_positions.id})
                    _logger.info("✅ Set fiscal position '%s' for order %s based on INVOICE country %s (direct match)", 
                               fiscal_positions.name, sale_order.name, invoice_country.name)
                else:
                    _logger.warning("❌ No fiscal position found for INVOICE country %s on order %s", 
                                  invoice_country.name, sale_order.name)
                
        except Exception as e:
            _logger.error("Error setting fiscal position from invoice country for order %s: %s", 
                         sale_order.name, str(e), exc_info=True)
