# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

from odoo import api, fields, models


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


class ResPartner(models.Model):
    _inherit = 'res.partner'

    @api.model
    def get_customer_duplicity_avoid_domain(self, vals):
        domain = []
        if vals.get("email"):
            domain += [("email", "=", vals.get('email'))]
        if vals.get('country_id'):
            domain += [("street", "=", vals.get('street')),
                       ("country_id", "=", vals.get('country_id')
                        ), ("state_id", "=", vals.get('state_id')),
                       ("zip", "=", vals.get('zip')), ("city", "=", vals.get('city')), ('type', '=', vals.get('type'))]
        return domain

    @api.model
    def check_customer_duplicity(self, vals):
        domain = self.get_customer_duplicity_avoid_domain(vals)
        customer_obj = False
        if domain:
            customer_obj = self.search(domain, limit=1)
        return customer_obj

    @api.model
    def create(self, vals):
        ctx = dict(self._context or {})
        instance_id = ctx.get('instance_id')
        ecomm_cannels = dict(
            self.env['connector.snippet']._get_ecomm_extensions()).keys()
        if any(key in ctx for key in ecomm_cannels):
            vals = self.customer_array(vals)
            check_cust_enable = self.env['connector.instance'].browse(
                instance_id).avoid_customer_duplicity
            if check_cust_enable:
                customer_obj = self.check_customer_duplicity(vals)
                if customer_obj.id and not vals.get('country_id'):
                    return customer_obj
                address_obj = self.check_customer_duplicity(vals)
                if address_obj.id and vals.get('country_id'):
                    return address_obj
        return super().create(vals)

    def write(self, vals):
        ctx = dict(self._context or {})
        ecomm_cannels = dict(
            self.env['connector.snippet']._get_ecomm_extensions()).keys()
        if any(key in ctx for key in ecomm_cannels):
            vals = self.customer_array(vals)
        return super().write(vals)

    def unlink(self):
        mappings = self.env['connector.partner.mapping'].search(
            [('name', 'in', self.ids)])
        self.env['connector.snippet'].delete_connector_mapping(
            'connector.partner.mapping', self, 'Customer', mappings)
        return super().unlink()

    def customer_array(self, data):
        import logging
        _logger = logging.getLogger(__name__)
        
        dic = {}
        stateModel = self.env['res.country.state']
        country_code = data.pop('country_code', False)
        country_name = data.pop('country_name', False)
        region = data.pop('region', False)
        state_name = data.pop('state_name', False)
        
        # Enhanced country resolution with debugging
        _logger.info("🌍 ADDRESS COUNTRY RESOLUTION DEBUG")
        _logger.info("Received - country_code: %s, country_name: %s", country_code, country_name)
        
        countryObj = None
        if country_code:
            countryObj = self.env['res.country'].search(
                [('code', '=', country_code)], limit=1)
            if countryObj:
                _logger.info("✅ Found country by code '%s': %s (ID: %s)", country_code, countryObj.name, countryObj.id)
                data['country_id'] = countryObj.id
            else:
                _logger.warning("❌ No country found for code: %s", country_code)
        
        # Fallback: try to find country by name if code didn't work
        if not countryObj and country_name:
            _logger.info("🔄 Trying to find country by name: %s", country_name)
            countryObj = self.env['res.country'].search(
                [('name', 'ilike', country_name)], limit=1)
            if countryObj:
                _logger.info("✅ Found country by name '%s': %s (ID: %s)", country_name, countryObj.name, countryObj.id)
                data['country_id'] = countryObj.id
            else:
                _logger.warning("❌ No country found for name: %s", country_name)
        
        if countryObj:
                # Handle state/region - try both region and state_name
                region_to_use = region or state_name
                if region_to_use:
                    region_to_use = _unescape(region_to_use)
                    _logger.info("🏛️  Looking for state/region: %s in country %s", region_to_use, countryObj.name)
                    
                    stateObj = stateModel.search([
                        ('name', '=', region_to_use),
                        ('country_id', '=', countryObj.id)
                    ], limit=1)
                    if stateObj:
                        _logger.info("✅ Found state: %s (ID: %s)", stateObj.name, stateObj.id)
                        data['state_id'] = stateObj.id
                    else:
                        _logger.info("🔄 State not found, creating new state: %s", region_to_use)
                        dic['name'] = region_to_use
                        dic['country_id'] = countryObj.id
                        code = region[:3].upper()
                        temp = code
                        stateObj = stateModel.search(
                            [('code', '=ilike', code)], limit=1)
                        counter = 0
                        while stateObj and counter < 100:
                            code = temp + str(counter)
                            stateObj = stateModel.search(
                                [('code', '=ilike', code)], limit=1)
                            counter = counter + 1
                        if counter == 100:
                            code = region.upper()
                        dic['code'] = code
                        stateObj = stateModel.create(dic)
                        data['state_id'] = stateObj.id
        tag = data.pop('tag', False)
        if tag:
            tag = _unescape(tag)
            tag_objs = self.env['res.partner.category'].search(
                [('name', '=', tag)], limit=1)
            if not tag_objs:
                tagId = self.env['res.partner.category'].create({'name': tag})
            else:
                tagId = tag_objs[0].id
            data['category_id'] = [(6, 0, [tagId])]
        data.pop('ecomm_id', None)
        if data.get('wk_company'):
            data['wk_company'] = _unescape(data['wk_company'])
        if data.get('name'):
            data['name'] = _unescape(data['name'])
        if data.get('email'):
            data['email'] = _unescape(data['email'])
        if data.get('street'):
            data['street'] = _unescape(data['street'])
        if data.get('street2'):
            data['street2'] = _unescape(data['street2'])
        if data.get('city'):
            data['city'] = _unescape(data['city'])
        return data

    def _handle_first_contact_creation(self):
        """ On creation of first contact for a company (or root) that has no address, assume contact address
        was meant to be company address """
        parent = self.parent_id
        address_fields = self._address_fields()
        if parent and (
            parent.is_company or not parent.parent_id) and len(
            parent.child_ids) == 1 and any(
            self[f] for f in address_fields) and not any(
                parent[f] for f in address_fields):
            addr_vals = self._update_fields_values(address_fields)
            parent.update_address(addr_vals)

    wk_company = fields.Char(string='Ecomm Company', size=128)
