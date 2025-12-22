#!/usr/bin/env python
# -*- coding: utf-8 -*-
#################################################################################
# Author      : Webkul Software Pvt. Ltd. (<https://webkul.com/>)
# Copyright(c): 2015-Present Webkul Software Pvt. Ltd.
# All Rights Reserved.
#
#
#
# This program is copyright property of the author mentioned above.
# You can`t redistribute it and/or modify it.
#
#
# You should have received a copy of the License along with this program.
# If not, see <https://store.webkul.com/license.html/>
#################################################################################

{
    'name': 'OOB - Opencart-Odoo Bridge',
    'version': '6.1.5',
    'author': 'Webkul Software Pvt. Ltd.',
    'summary': 'Bi-directional synchronization with Opencart',
    'description': """
OOB - Opencart-Odoo Bridge
==============================
This module establish bridge between your Odoo and Opencart and allows bi-directional synchronization of your data between them.

NOTE: You need to install a corresponding 'Opencart-Odoo Bridge' plugin on your Opencart too,
in order to work this module perfectly.

Key Features
------------
* export/update "all" or "selected" or "multi-selected" products,with images, from Odoo to Opencart with a single click.
* export/update "all" or "selected" or "multi-selected" categories from Odoo to Opencart with a single click.
* maintain order`s statuses with corressponding orders on Opencart.(if the order is created from Opencart)
* export/update "all" or "selected" or "multi-selected" categories from Odoo to Opencart with a single click.

Dashboard / Reports:
------------------------------------------------------
* Orders created from Opencart on specific date-range

For any doubt or query email us at support@webkul.com or raise a Ticket on http://webkul.com/ticket/
    """,
    'website': 'http://www.webkul.com',
    'images': [],
    'depends': ['bridge_skeleton'],
    'category': 'OOB',
    'sequence': 1,
    'data': [
        'views/connector_instance.xml',
        'views/connector_product_mapping.xml',
        'views/stock_location_view.xml'
    ],
    'installable': True,
    'application': True,
    'auto_install': False,
    "external_dependencies":  {'python': ['requests']},
}
