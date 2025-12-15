# -*- coding: utf-8 -*-
#################################################################################
#
#   Patched Connector for OpenCart API login
#
#################################################################################

import logging
import requests

from odoo import api, fields, models

_logger = logging.getLogger(__name__)

API_PATH = 'index.php?route=api/login'


class ConnectorInstance(models.Model):
    _inherit = 'connector.instance'

    session_key = fields.Char('Opencart Api Token', readonly=True)
    pwd = fields.Text('Opencart Api Key')  # store OpenCart API key
    product_configurable = fields.Selection(
        [('template', 'Product Template'), ('variants', 'Product Variants')],
        default='template'
    )

    @api.model
    def create(self, vals):
        if 'pwd' in vals:
            vals['pwd'] = vals['pwd'].strip()
            _logger.debug("ConnectorInstance.create: stripped pwd")
        if 'user' in vals and not vals['user'].endswith('/'):
            vals['user'] += '/'
        return super().create(vals)

    def write(self, vals):
        if 'pwd' in vals:
            vals['pwd'] = vals['pwd'].strip()
            _logger.debug("ConnectorInstance.write: stripped pwd")
        if 'user' in vals and not vals['user'].endswith('/'):
            vals['user'] += '/'
        return super().write(vals)

    def test_opencart_connection(self):
        """
        Test connection from Odoo to OpenCart using native API login.
        """
        text = 'Test connection unsuccessful. Please check the OpenCart API credentials.'
        status = 'OpenCart Connection Unsuccessful'
        self.connection_status = False

        url = self.user + API_PATH
        data = {
            "username": "Default",   # always fixed
            "key": self.pwd.strip() if self.pwd else ""
        }
        headers = {
            "Content-Type": "application/x-www-form-urlencoded"
        }

        try:
            resp = requests.post(url, data=data, headers=headers, timeout=15)
            _logger.debug("OpenCart login URL: %s", url)
            _logger.debug("OpenCart login response: %s", resp.text)

            if resp.status_code in [200, 201]:
                body = resp.json()

                if isinstance(body, dict):
                    # Standard OpenCart response
                    if body.get("success") and body.get("api_token"):
                        token = body["api_token"]
                        self.write({
                            "session_key": token,
                            "connection_status": True,
                            "status": "Connected to OpenCart API"
                        })
                        text = "Connection successful. API token stored."
                        status = "OpenCart Connection Successful"
                    else:
                        text = f"Login failed. Response: {body}"

                elif isinstance(body, list) and len(body) >= 2:
                    # Legacy/alternate response format: [token, status]
                    token = str(body[0])
                    status_text = body[1]
                    if status_text:
                        self.write({
                            "session_key": token,
                            "connection_status": True,
                            "status": status_text
                        })
                        text = "Connection successful (legacy format)."
                        status = "OpenCart Connection Successful"
                    else:
                        text = f"Login failed. Response: {body}"
                else:
                    text = f"Unexpected response format: {body}"
            else:
                text = f"HTTP error {resp.status_code}: {resp.text}"

        except Exception as e:
            text = f"Error during connection: {e}"

        return self.env['message.wizard'].genrated_message(text)

    def _create_opencart_connection(self):
        """
        Return connection dict for downstream syncs.
        """
        status = False
        instance_id = self._context.get("instance_id")
        session_key = None
        url = None
        product_configurable = "template"

        if instance_id:
            instance = self.browse(instance_id)
            status = True
            url = instance.user + API_PATH
            session_key = instance.session_key
            product_configurable = instance.product_configurable

        return {
            "status": status,
            "url": url,
            "session_key": session_key,
            "product_configurable": product_configurable,
        }
