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

"""
	Oobapi is a library for Python to interact with the Opencart's Web Service API.

	Questions, comments? http://webkul.com/ticket/index.php
"""

__author__ = "Vikash Mishra <vikash.kumar43@webkul.com>"
__version__ = "0.1.0"

import urllib
import warnings
import requests
import json
from . import xml2dict
from . import dict2xml
from . import unicode_encode
import base64
# from cStringIO import StringIO
# from io import StringIO
# import unicode_encode
import logging
_logger = logging.getLogger(__name__)

class OpencartWebService(object):
	"""
		Interacts with the Opencart WebService API, use XML for messages
	"""
	MIN_COMPATIBLE_VERSION = '2.1.0.1'
	MAX_COMPATIBLE_VERSION = '3.2.0.x'

	def __init__(self, debug=False, headers=None, client_args=None):
		"""
		Create an instance of OpencartWebService.

		In your code, you can use :
		from oobapi import OpencartWebService, OpencartWebServiceError

		try:
			opencart = OpencartWebService.new('http://localhost:8080/api', 'BVWPFFYBT97WKM959D7AVVD0M4815Y1L')
		except OpencartWebServiceError, e:
			print str(e)
			...

		@param api_url: Root URL for the shop
		@param api_key: Authentification key
		@param debug: Debug mode Activated (True) or deactivated (False)
		@param headers: Custom header, is a dict accepted by httplib2 as instance
		@param client_args: Dict of extra arguments for HTTP Client (httplib2) as instance {'timeout': 10.0}
		"""
		# if client_args is None:
		#     client_args = {}
		# self._api_url = api_url

		# if not self._api_url.endswith('/'):
		#     self._api_url += '/'
		#
		# if not self._api_url.endswith('/api/'):
		#     self._api_url += 'api/'

		self.debug = debug

		self.headers = headers
		if self.headers is None:
			self.headers = {'User-agent': 'Opencartapi: Python Opencart Library'}

		self.client = requests.session()
		# self.client.auth=(api_key, '')


	def _execute(self, url, method, data=None, files=None, add_headers=None):
		"""
		Execute a request on the Opencart Webservice

		@param url: full url to call
		@param method: GET, POST, PUT, DELETE, HEAD
		@param data: JSON string for POST/PUT requests
		@param files: should contain {'image': (img_filename, img_file)}
		@param add_headers: additional headers merged on the instance's headers
		@return: response object
		"""
		if add_headers is None:
			add_headers = {}

		if self.debug and data and method != 'POST':
			_logger.debug("Execute url: %s / method: %s\nbody: %s", url, method, data)

		request_headers = self.headers.copy()
		request_headers.update(add_headers)

		# Send JSON data if Content-Type is application/json
		if not files:
			if request_headers.get('Content-Type') == 'application/json':
				# data is already a JSON string from json.dumps(), send as-is
				r = self.client.request(method, unicode_encode.encode(url), data=data, headers=request_headers)
			else:
				r = self.client.request(method, unicode_encode.encode(url), data=data, headers=request_headers)
		else:
			r = self.client.request(method, url, files=files, headers={'User-agent': 'Opencartapi: Python Opencart Library'})

		_logger.debug("Response code: %s", r.status_code)
		_logger.debug("Response body: %s", r.text[:500])

		return r




class OpencartWebServiceDict(OpencartWebService):
	"""
	Interacts with the Opencart WebService API, use dict for messages
	"""

	def get_session_key(self, api_url, params, debug=False, headers=None):
		# """
		# Send API request with JSON payload
		# """
		self._api_url = api_url
		self.debug = debug
		self.headers = headers
		if self.headers is None:
			self.headers = {'User-agent': 'Opencartapi: Python Opencart Library'}
		headers = {'Content-Type': 'application/json'}
		_logger.info("API Request to: %s", api_url)
		_logger.debug("API Request payload: %s", params)
		# Convert params to JSON string if it's not already a string
		# Some callers (product_sync) pass pre-encoded JSON, others pass dicts
		if isinstance(params, str):
			json_data = params
		else:
			json_data = json.dumps(params)
		r = self._execute(api_url, 'POST', data=json_data, add_headers=headers)
		return r

	def validate_session_key(self, api_url, params, debug=False, headers=None):
		# """
		# """
		self._api_url = api_url
		self.debug = debug
		self.headers = headers
		if self.headers is None:
			self.headers = {'User-agent': 'Opencartapi: Python Opencart Library'}
		headers = {'Content-Type': 'application/x-www-form-urlencoded'}
		r = self._execute(api_url, 'POST', data=params, add_headers=headers)
		# self.client = requests.Session()
		# self.client.auth=(params, '')
		# client = Session()
		# print client
		# req = Request('POST', api_url, data=params, headers={})
		# prepped = req.prepare()
		# resp = client.send(prepped,
		#     verify=False,
		# )
		return r
# "SELECT * FROM oc_category c LEFT JOIN oc_category_description cd ON (c.category_id = cd.category_id) LEFT JOIN oc_category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '(int)$parent_id' AND cd.language_id = '(int)$this->config->get('config_language_id')' AND c2s.store_id = '(int)$this->config->get('config_store_id')'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)"
