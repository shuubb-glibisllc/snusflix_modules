# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

from odoo import fields, models


class StockLocation(models.Model):
    _inherit = 'stock.location'

    opencart_sync = fields.Boolean(
        string="Sync to OpenCart",
        default=False,
        help="If enabled, quantities from this location will be included in OpenCart inventory synchronization."
    )