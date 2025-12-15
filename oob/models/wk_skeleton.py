# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

from odoo import api, models


class WkSkeleton(models.TransientModel):
    _inherit = "wk.skeleton"

    @api.model
    def get_ecomm_href(self, getcommtype=False):
        href_list = super().get_ecomm_href(getcommtype)
        if getcommtype == 'opencart':
            href_list = {
                'user_guide': 'https://store.webkul.com/Opencart-OpenERP-Connector.html',
                'rate_review': 'https://store.webkul.com/Opencart-OpenERP-Connector.html#tabreviews',
                'extension': 'https://store.webkul.com/Opencart-OpenERP-Connector.html',
                'name': 'OPENCART',
                'short_form': 'Oob',
                'img_link': '/oob/static/src/img/icon.png'
            }
        return href_list
