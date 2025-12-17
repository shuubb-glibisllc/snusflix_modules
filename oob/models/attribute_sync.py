# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

# Attribute Sync Operation

from odoo import api, models
import logging
_logger = logging.getLogger(__name__)


class ConnectorSnippet(models.TransientModel):
    _inherit = "connector.snippet"

    def create_opencart_product_attribute(self, name, odoo_id, connection, ecomm_attribute_code):
        """ create opencart product attribute from odoo
        @params : attribute name, attribute id, opencart connection dictionay, ecommerce attribute code
        return : dictionary 

        """
        session_key = connection.get('session_key', False)
        opencart = connection.get('opencart', False)
        url = connection.get('url', False)
        status = True
        ecomm_id = False
        attribute_id = self.env['product.attribute'].browse(int(odoo_id))
        error = ''
        if session_key and opencart and url:
            _logger.info("Creating OpenCart attribute: %s (Odoo ID: %s)", name, odoo_id)
            route = 'option'
            attrdDetail = dict({
                'name': name,
                'odoo_id': odoo_id,
                'type': attribute_id.display_type or 'text',
                'sort_order': attribute_id.sequence or '1'

            })
            attrdDetail['session'] = session_key
            try:
                resp = opencart.get_session_key(url+route, attrdDetail)
                resp = resp.json()
                _logger.info("OpenCart attribute creation response: %s", resp)

                # Handle variable-length responses
                # Success (create): [message, option_id, true] - 3 elements
                # Error: [error_message, false] - 2 elements
                if len(resp) >= 3:
                    key = str(resp[0])
                    oc_id = resp[1]
                    status = resp[2]
                    if status:
                        ecomm_id = oc_id
                        _logger.info("Attribute '%s' created successfully with OpenCart ID: %s", name, oc_id)
                    else:
                        status = False
                        _logger.warning("Attribute '%s' creation returned status=False", name)
                elif len(resp) >= 2:
                    error_msg = str(resp[0])
                    status = resp[1]
                    if not status:
                        error = error_msg
                        _logger.error("OpenCart API error for attribute '%s': %s", name, error_msg)
                else:
                    status = False
                    error = "Invalid API response format"
                    _logger.error("Invalid API response for attribute '%s': %s", name, resp)
            except Exception as e:
                _logger.error("Failed to create OpenCart attribute '%s' (ID: %s): %s",
                             name, odoo_id, str(e), exc_info=True)
                status = False
                error = str(e)
        else:
            status = False
        return {
            'status': status,
            'ecomm_id': ecomm_id,
            'error': error
        }

    def create_opencart_product_attribute_value(self, ecomm_id, attribute_obj, ecomm_attribute_code, instance_id, connection):
        """ create opencart product attribute Value from odoo
        @params : option id opencart, attribute object odoo, option code, instance id, opencart connection dictionary
        return : dictionary 

        """
        session_key = connection.get('session_key', False)
        opencart = connection.get('opencart', False)
        url = connection.get('url', False)
        status = True
        error = ''
        if session_key and opencart and url:
            _logger.info("Creating OpenCart attribute values for option ID: %s (%s values)",
                        ecomm_id, len(attribute_obj.value_ids))
            for attribute_value_id in attribute_obj.value_ids:
                if not self.env['connector.option.mapping'].search([('odoo_id', '=', attribute_value_id.id),
                                                                    ('instance_id', '=', instance_id)]):
                    _logger.debug("Creating attribute value: %s (ID: %s)",
                                attribute_value_id.name, attribute_value_id.id)
                    route = 'optionvalue'
                    attrdDetail = dict({
                        'name': attribute_value_id.name,
                        'odoo_id': attribute_value_id.id,
                        'option_id': ecomm_id,
                        'sort_order':  attribute_value_id.sequence or '1'
                    })
                    attrdDetail['session'] = session_key
                    try:
                        resp = opencart.get_session_key(url+route, attrdDetail)
                        resp = resp.json()
                        _logger.debug("OpenCart attribute value response: %s", resp)

                        # Handle variable-length responses
                        # Success: [message, option_value_id, true] - 3 elements
                        # Error: [error_message, false] - 2 elements
                        if len(resp) >= 3:
                            key = str(resp[0])
                            ecomm_value_id = resp[1]
                            status = resp[2]
                            if status:
                                self.create_odoo_connector_mapping('connector.option.mapping',
                                                                   ecomm_value_id,
                                                                   attribute_value_id.id, instance_id,
                                                                   ecomm_attribute_id=ecomm_id,
                                                                   odoo_attribute_id=attribute_obj.id)
                                _logger.info("Attribute value '%s' created with OpenCart ID: %s",
                                           attribute_value_id.name, ecomm_value_id)
                        elif len(resp) >= 2:
                            error_msg = str(resp[0])
                            status = resp[1]
                            if not status:
                                error = error_msg
                                _logger.error("OpenCart API error for attribute value '%s': %s",
                                            attribute_value_id.name, error_msg)
                        else:
                            status = False
                            error = "Invalid API response format"
                            _logger.error("Invalid API response for attribute value '%s': %s",
                                        attribute_value_id.name, resp)
                    except Exception as e:
                        _logger.error("Failed to create attribute value '%s': %s",
                                    attribute_value_id.name, str(e), exc_info=True)
                        status = False
                        error = str(e)
            return {
                'status': True,
                'error': error
            }
