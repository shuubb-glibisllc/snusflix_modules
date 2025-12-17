# -*- coding: utf-8 -*-
##########################################################################
#
#   Copyright (c) 2015-Present Webkul Software Pvt. Ltd. (<https://webkul.com/>)
#   See LICENSE file for full copyright and licensing details.
#   License URL : <https://store.webkul.com/license.html/>
#
##########################################################################

# Product Sync Operation
import json
from odoo import api, models
import warnings
import logging
_logger = logging.getLogger(__name__)


class ConnectorSnippet(models.TransientModel):
    _inherit = "connector.snippet"

    def _export_opencart_specific_template(self, obj_pro, instance_id, channel, connection):
        """
        @param code: Obj pro, instance id , channel , connection
        @param context: A standard dictionary
        @return: Dictionary
        """
        session_key = connection.get('session_key', False)
        opencart = connection.get('opencart', False)
        url = connection.get('url', False)
        status = False
        ecomm_id = False
        oc_categ_id = 0
        product_data = {}
        is_variants = False
        error = ''
        variant_data = False

        if obj_pro and session_key and opencart and url:
            _logger.info("Starting OpenCart product export for template: %s (ID: %s)",
                        obj_pro.name, obj_pro.id)
            try:
                if obj_pro.attribute_line_ids:
                    product_data = self.get_attribute_data(
                        obj_pro, instance_id)
                    if obj_pro.product_variant_count > 1:
                        variant_data = self.sync_variant(obj_pro, instance_id)
                        _logger.info(
                            f"=======VARIANTDATA========{variant_data[1].get('option_value_ids')}")
                    if variant_data and 'option_value_ids' in variant_data[1]:
                        is_variants = True
                else:
                    product_data['variant_id'] = str(
                        obj_pro.product_variant_ids[0].id)

                prod_catg = []
                for j in obj_pro.connector_categ_ids.categ_ids:
                    oc_categ_id = self.sync_categories(
                        j, instance_id, channel, connection)
                    prod_catg.append(oc_categ_id)

                if obj_pro.categ_id.id:
                    oc_categ_id = self.sync_categories(
                        obj_pro.categ_id, instance_id, channel, connection)
                    prod_catg.append(oc_categ_id)

                product_data['sku'] = obj_pro.default_code or 'Ref Odoo %s' % obj_pro.id
                product_data['model'] = obj_pro.default_code or 'Ref Odoo %s' % obj_pro.id
                product_data['name'] = obj_pro.name
                product_data['keyword'] = obj_pro.name
                product_data['description'] = obj_pro.description or ' '
                product_data['meta_keyword'] = obj_pro.name
                product_data['meta_description'] = obj_pro.description or obj_pro.name
                product_data['tag'] = obj_pro.name
                product_data['ean'] = obj_pro.barcode or ' '
                product_data['price'] = obj_pro.list_price or 0.00
                product_data['quantity'] = self.env['connector.snippet'].get_quantity(
                    obj_pro.product_variant_ids[0], instance_id)
                product_data['weight'] = obj_pro.weight or 0.00
                product_data['length'] = 0
                product_data['width'] = 0
                product_data['height'] = 0
                product_data['status'] = 1
                product_data['tax_class_id'] = 0
                product_data['stock_status_id'] = 7
                product_data['erp_product_id'] = obj_pro.id
                product_data['product_category'] = list(set(prod_catg))
                product_data['erp_template_id'] = obj_pro.id
                product_data['product_image'] = obj_pro.image_1920
                product_data['minimum'] = '1'
                product_data['subtract'] = '1'
                product_data['product_variant_count'] = obj_pro.product_variant_count

                if obj_pro.product_variant_count > 1:
                    product_data['product_variant_ids'] = obj_pro.product_variant_ids.ids
                if variant_data:
                    product_data['oc_variants_data'] = variant_data
                if product_data['product_image']:
                    product_data['product_image'] = product_data['product_image'].decode(
                    )

                product_data['session'] = session_key
                pro = self.prodcreate(
                    url, opencart, obj_pro, product_data, instance_id, is_variants)
                ecomm_id = pro[1]
                status = True
                _logger.info("Product template '%s' exported successfully with OpenCart ID: %s",
                           obj_pro.name, ecomm_id)
            except Exception as e:
                _logger.error("Failed to export OpenCart product template '%s' (ID: %s): %s",
                            obj_pro.name, obj_pro.id, str(e), exc_info=True)
                error = str(e)

        return {
            'status': status,
            'ecomm_id': ecomm_id,
            'error': error
        }

    def prodcreate(self, url, session, pro_id, put_product_data, instance_id, is_variants):
        """
        calls api product create for opencart
        @params: opencart url, OpencartWebservice object , product id , data dictioanry , instance_id , is_variants
        $returns : list
        """

        route = 'product'
        pro = 0
        product_name = put_product_data.get('name', 'Unknown')
        variant_count = put_product_data.get('product_variant_count', 0)

        _logger.info("Creating OpenCart product: %s (variants: %s)", product_name, variant_count)

        try:
            param = json.dumps(put_product_data)
            resp = session.get_session_key(url + route, param)
            _logger.debug("OpenCart product API response (first 500 chars): %s", str(resp.text)[:500])

            resp = resp.json()

            # Handle variable-length responses
            # Success: [message, product_data, true] - 3 elements
            # Error: [error_message, false] - 2 elements
            if len(resp) >= 3:
                key = str(resp[0])
                oc_id = resp[1]
                status = resp[2]
                _logger.info("OpenCart product creation response - Status: %s, Data: %s", status, oc_id)

                if not status:
                    _logger.warning("Product creation failed for '%s': %s", product_name, key)
                    return [0, f"{str(pro_id)} - {key}"]
            elif len(resp) >= 2:
                error_msg = str(resp[0])
                status = resp[1]
                if not status:
                    _logger.error("OpenCart API error for product '%s': %s", product_name, error_msg)
                    return [0, f"{str(pro_id)} - {error_msg}"]
            else:
                _logger.error("Invalid API response format for product '%s': %s", product_name, resp)
                return [0, f"Invalid API response: {str(resp)}"]
        except Exception as e:
            _logger.error("Failed to create OpenCart product '%s': %s", product_name, str(e), exc_info=True)
            return [0, f"Error: {str(e)}"]

        if status:
            pro = oc_id
            self.create_odoo_connector_mapping(
                'connector.template.mapping',
                pro['product_id'],
                put_product_data['erp_template_id'],
                instance_id,
                is_variants=is_variants,
                name=int(put_product_data['erp_template_id'])
            )

            if pro.get('merge_data'):
                for k in pro['merge_data']:
                    self.create_odoo_connector_mapping(
                        'connector.product.mapping',
                        pro['product_id'],
                        int(k),
                        instance_id,
                        odoo_tmpl_id=put_product_data['erp_template_id'],
                        ecomm_option_id=pro['merge_data'][k],
                        name=int(k)
                    )
            elif put_product_data.get('variant_id'):
                self.create_odoo_connector_mapping(
                    'connector.product.mapping',
                    pro['product_id'],
                    put_product_data['variant_id'],
                    instance_id,
                    odoo_tmpl_id=put_product_data['erp_template_id'],
                    ecomm_option_id=0,
                    name=put_product_data['variant_id']
                )

            return [1, pro['product_id']]

    def get_attribute_data(self, obj_pro, instance_id):
        """
        return variant data for product template
        @params: template object, instance id
        $returns : dictionary
        """
        option_val_obj = self.env['connector.option.mapping']
        option_obj = self.env['connector.attribute.mapping']
        product_data = {}
        product_option_data = []

        if obj_pro:
            has_attributes = obj_pro.attribute_line_ids

            if has_attributes:
                for i in has_attributes:
                    oc_attr_value_ids = []
                    option_name = i.attribute_id.name
                    erp_attr_id = i.attribute_id.id
                    option_dic = {"name": option_name}
                    attr_search = option_obj.search(
                        [('odoo_id', '=', erp_attr_id), ('instance_id', '=', instance_id)])

                    if attr_search:
                        option_id = attr_search[0].ecomm_id
                        option_dic['id'] = option_id
                        for k in i.value_ids:
                            map_search = option_val_obj.search(
                                [('odoo_id', '=', k.id), ('instance_id', '=', instance_id)])

                            if map_search:
                                option_val_id = map_search[0].ecomm_id
                                # _logger.info(f"==============attr_search================{map_search}")
                                value_dict = {
                                    # 'quantity': str(self.env['connector.snippet'].get_quantity(k, instance_id)),
                                    # 'price_prefix': price_prefix,
                                    # 'price': str(price_extra),
                                    'option_value_id': str(option_val_id),
                                    # 'erp_product_id': str(erp_product_id),
                                }

                                oc_attr_value_ids.append(value_dict)
                            else:
                                raise Warning((
                                    "Products Attributes Values have not been mapped. Please map the Odoo Attribute Values from OpenCart!!\n Odoo Attribute Values ID: %s") % (k.product_template_attribute_value_ids.id))
                    else:
                        raise Warning((
                            "Products Attributes have not been mapped. Please map the Odoo Attributes from OpenCart!!! \n Odoo Attribute ID: %s") % (erp_attr_id))
                    if option_id:
                        option_dic['value_ids'] = oc_attr_value_ids
                        product_option_data.append(option_dic)
            else:
                product_data['variant_id'] = str(
                    obj_pro.product_variant_ids.id)

        if product_option_data:
            product_data['oc_options'] = product_option_data
        return product_data

    def sync_variant(self, obj_pro, instance_id):
        """
        Returns variant data for product template
        @params: product template object, instance id
        @return: list of variant data
        """
        variant_data = []
        if obj_pro:
            for k in obj_pro.product_variant_ids:
                search_list = []
                for attr_val in k.product_template_variant_value_ids:
                    attribute_value_id = attr_val.product_attribute_value_id[0].id
                    search_attr = self.env['connector.option.mapping'].search(
                        [('odoo_id', '=', attribute_value_id)])

                    if search_attr:
                        search_list.append(search_attr.ecomm_id)

                erp_product_id = k.id
                price_extra = abs(k.price_extra)
                price_prefix = '-' if k.price_extra < 0 else '+'

                value_dict = {
                    'name': k.name,
                    'quantity': str(self.env['connector.snippet'].get_quantity(k, instance_id)),
                    'price_prefix': price_prefix,
                    'price': str(price_extra),
                    'erp_product_id': str(erp_product_id),
                    'standard_price': int(k.standard_price),
                    'option_value_ids': str(search_list)
                }

                variant_data.append(value_dict)
        return variant_data

    def _update_opencart_specific_template(self, obj_pro_mapping, instance_id, channel, connection):
        """
        update product template and its variants
        @param code: Obj pro, instance id , channel , connection
        @param context: A standard dictionary
        @return: Dictionary
        """
        session_key = connection.get('session_key', False)
        opencart = connection.get('opencart', False)
        url = connection.get('url', False)
        status = True
        oc_categ_id = 0
        product_data = {}
        product_connector = self.env['connector.product.mapping']
        is_variants = False
        ecomm_product_id = obj_pro_mapping.ecomm_id
        obj_pro = obj_pro_mapping.name
        route = 'product'
        error = ''
        if obj_pro and session_key and opencart and url:
            try:
                if obj_pro.attribute_line_ids:
                    product_data = self.get_attribute_data(
                        obj_pro, instance_id)
                    if obj_pro.product_variant_count > 1:
                        variant_data = self.sync_variant(obj_pro, instance_id)
                    if (variant_data[1].get('option_value_ids')):
                        obj_pro_mapping.is_variants = True
                        is_variants = True
                    else:
                        obj_pro_mapping.is_variants = False
                else:
                    product_data['variant_id'] = str(
                        obj_pro.product_variant_ids[0].id)
                oc_categ_id = 0
                prod_catg = []
                for j in obj_pro.connector_categ_ids.categ_ids:
                    oc_categ_id = self.sync_categories(
                        j, instance_id, channel, connection)
                    prod_catg.append(oc_categ_id)
                if obj_pro.categ_id.id:
                    oc_categ_id = self.sync_categories(
                        obj_pro.categ_id, instance_id, channel, connection)
                    prod_catg.append(oc_categ_id)
                product_data['product_id'] = ecomm_product_id
                product_data['sku'] = obj_pro.default_code or 'Ref Odoo %s' % obj_pro.id
                product_data['name'] = obj_pro.name
                product_data['keyword'] = obj_pro.name
                product_data['description'] = obj_pro.description or ' '
                product_data['meta_keyword'] = obj_pro.name
                product_data['meta_description'] = obj_pro.description or obj_pro.name
                product_data['tag'] = obj_pro.name
                product_data['ean'] = obj_pro.barcode or ' '
                product_data['price'] = obj_pro.list_price or 0.00
                product_data['quantity'] = self.env['connector.snippet'].get_quantity(
                    obj_pro.product_variant_ids[0], instance_id)
                product_data['weight'] = obj_pro.weight or 0.00
                product_data['length'] = 0
                product_data['width'] = 0
                product_data['height'] = 0
                product_data['status'] = 1
                product_data['tax_class_id'] = 0
                product_data['stock_status_id'] = 7
                product_data['erp_product_id'] = obj_pro.id
                product_data['product_category'] = list(set(prod_catg))
                product_data['erp_template_id'] = obj_pro.id
                product_data['product_image'] = obj_pro.image_1920
                if obj_pro.product_variant_count > 1:
                    product_data['product_variant_ids'] = obj_pro.product_variant_ids.ids
                if variant_data:
                    product_data['oc_variants_data'] = variant_data
                if product_data['product_image']:
                    product_data['product_image'] = product_data['product_image'].decode(
                    )
                product_data['session'] = session_key
                param = json.dumps(product_data)
                resp = opencart.get_session_key(url+route, param)
                resp = resp.json()

                # Handle variable-length responses
                # Success: [message, product_data, true] - 3 elements
                # Error: [error_message, false] - 2 elements
                if len(resp) >= 3:
                    key = str(resp[0])
                    oc_id = resp[1]
                    status = resp[2]
                elif len(resp) >= 2:
                    error_msg = str(resp[0])
                    status = resp[1]
                    if not status:
                        error = error_msg
                        _logger.error("OpenCart API error updating product: %s", error_msg)
                else:
                    status = False
                    error = "Invalid API response format"
                    _logger.error("Invalid API response for product update: %s", resp)

                if not status:
                    status = False
                if status:
                    for k in oc_id['merge_data']:
                        search = product_connector.search(
                            [('odoo_id', '=', int(k)), ('instance_id', '=', instance_id)])
                        if search and is_variants:
                            search = search.unlink()
                        self.create_odoo_connector_mapping('connector.product.mapping',
                                                           ecomm_product_id,
                                                           int(k),
                                                           instance_id,
                                                           odoo_tmpl_id=product_data['erp_template_id'],
                                                           ecomm_option_id=int(
                                                               oc_id['merge_data'][k]),
                                                           name=int(k))

                    obj_pro_mapping.need_sync = 'No'
            except Exception as e:
                status = False
                error = str(e)
        return {
            'status': status,
            'error': error
        }
