# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

# Category Sync Operation

from odoo import api, models


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
            route = 'category'
            catgdetail = dict({
                'name': name,
                'erp_category_id': odoo_id
            })
            if parent_categ_id == 1:
                catgdetail['parent_id'] = 0
            else:
                catgdetail['parent_id'] = parent_categ_id
            catgdetail['session'] = session_key
            try:
                resp = opencart.get_session_key(url+route, catgdetail)
                resp = resp.json()
                key = str(resp[0])
                oc_id = resp[1]
                status = resp[2]
                if status:
                    ecomm_id = oc_id
                else:
                    status = False
            except Exception as e:
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
            get_category_data = {}
            route = 'category'
            cat = ''
            name = vals.get('name', '')
            cat_data = False
            get_category_data['category_id'] = ecomm_id
            get_category_data['session'] = session_key
            get_category_data['name'] = name
            if vals.get('parent_id'):
                if vals['parent_id'] == 1:
                    vals['parent_id'] = 0
                get_category_data['parent_id'] = vals['parent_id']
            try:
                resp = opencart.get_session_key(url+route, get_category_data)
                resp = resp.json()
                key = str(resp[0])
                status = resp[1]
                if not status:
                    status = False
            except Exception as e:
                status = False
                error = str(e)
        else:
            status = False
        return {
            'status': status,
            'error': error
        }
