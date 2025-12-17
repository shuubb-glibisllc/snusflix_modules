# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

# Category Sync Operation

import logging
from odoo import api, models

_logger = logging.getLogger(__name__)


class ConnectorSnippet(models.TransientModel):
    _inherit = "connector.snippet"

    def create_opencart_category(self, odoo_id, parent_categ_id, name, connection):
        """ create opencart product category from odoo
        @params : odoo category id, openerp parent categ id, category name, opencart connection dictionay
        return : dictionary 

        """
        session_key = connection.get('session_key', False)
        opencart = connection.get('opencart', False)
        url = connection.get('url', False)
        status = True
        ecomm_id = False
        error = ''
        if session_key and opencart and url:
            _logger.info("Creating OpenCart category: %s (Odoo ID: %s, Parent: %s)",
                         name, odoo_id, parent_categ_id)
            route = 'category'
            catgdetail = dict({
                'name': name,
                'erp_category_id': odoo_id,
                'description': name,
                'meta_keyword': name,
                'meta_description': name,
                'sort_order': 1,
                'status': 1
            })
            if parent_categ_id == 1:
                catgdetail['parent_id'] = 0
            else:
                catgdetail['parent_id'] = parent_categ_id
            catgdetail['session'] = session_key
            try:
                resp = opencart.get_session_key(url+route, catgdetail)
                resp = resp.json()
                _logger.info("OpenCart category creation response: %s", resp)

                # Handle variable-length responses
                # Success (create): [message, category_id, true] - 3 elements
                # Error: [error_message, false] - 2 elements
                if len(resp) >= 3:
                    # Successful creation with category ID
                    key = str(resp[0])
                    oc_id = resp[1]
                    status = resp[2]
                    if status:
                        ecomm_id = oc_id
                        _logger.info("Category '%s' created successfully with OpenCart ID: %s", name, oc_id)
                    else:
                        status = False
                        _logger.warning("Category '%s' creation returned status=False", name)
                elif len(resp) >= 2:
                    # Error response or update response
                    error_msg = str(resp[0])
                    status = resp[1]
                    if status:
                        _logger.info("Category '%s' operation successful: %s", name, error_msg)
                    else:
                        error = error_msg
                        _logger.error("OpenCart API error for category '%s': %s", name, error_msg)
                else:
                    status = False
                    error = "Invalid API response format"
                    _logger.error("Invalid API response for category '%s': %s", name, resp)
            except Exception as e:
                _logger.error("Failed to create OpenCart category '%s' (ID: %s): %s",
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

    def update_opencart_category(self, vals, ecomm_id, connection):
        """ update opencart product category from odoo
        @params : vals(name, parent_id), opencart category id, opencart connection dictionay
        return : dictionary 

        """
        session_key = connection.get('session_key', False)
        opencart = connection.get('opencart', False)
        url = connection.get('url', False)
        status = True
        error = ''
        if session_key and opencart and url:
            _logger.info("Updating OpenCart category ID %s: %s", ecomm_id, vals)
            get_category_data = {}
            route = 'category'
            cat = ''
            name = vals.get('name', '')
            cat_data = False
            get_category_data['category_id'] = ecomm_id
            get_category_data['session'] = session_key
            get_category_data['name'] = name
            get_category_data['description'] = vals.get('description', name)
            get_category_data['status'] = 1
            if vals.get('parent_id'):
                if vals['parent_id'] == 1:
                    vals['parent_id'] = 0
                get_category_data['parent_id'] = vals['parent_id']
            try:
                resp = opencart.get_session_key(url+route, get_category_data)
                resp = resp.json()
                _logger.info("OpenCart category update response: %s", resp)

                # Handle variable-length responses
                # Success (update): [message, true] - 2 elements
                # Error: [error_message, false] - 2 elements
                if len(resp) >= 2:
                    message = str(resp[0])
                    status = resp[1]
                    if status:
                        _logger.info("Category ID %s updated successfully: %s", ecomm_id, message)
                    else:
                        error = message
                        _logger.error("OpenCart API error updating category ID %s: %s", ecomm_id, message)
                else:
                    status = False
                    error = "Invalid API response format"
                    _logger.error("Invalid API response for category update ID %s: %s", ecomm_id, resp)
            except Exception as e:
                _logger.error("Failed to update OpenCart category ID %s: %s",
                             ecomm_id, str(e), exc_info=True)
                status = False
                error = str(e)
        else:
            status = False
        return {
            'status': status,
            'error': error
        }
