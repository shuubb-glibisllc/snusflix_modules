<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

require_once 'oob_log.php';

class ModelCatalogErpOrder extends Model {

    public function check_spec_order($order_id, $userId, $client, $db, $pwd, $cart_user, $wkproducttype) {
        $this->load->model('catalog/connection');
        $check_order_isSync = $this->db->query("SELECT id FROM ".DB_PREFIX."erp_order_merge WHERE opencart_order_id = '".$order_id."'")->row;

        if($check_order_isSync AND isset($check_order_isSync['id']))
            return;

        //load opencart order model and get order info
        $this->load->model('sale/order');
        $This_order = $this->model_sale_order->getOrder($order_id);

        //get currency id from orderdata
        $currency_id = $This_order['currency_id'];
        $currency_code = $This_order['currency_code'];

        //load currency model and check specific
        $this->load->model('catalog/erp_currency');
        $pricelist_id = $this->model_catalog_erp_currency->check_specific_currency($currency_id, $currency_code, $userId, $client, $db, $pwd);
        if(!$pricelist_id){
            return array(0,0,"Odoo pricelist id not found");
        }

        //it will return an array of (partner_id, partner_invoice_id, partner_shipping_id)
        $erpAddressArray = $this->getErpOrderAddresses($This_order, $userId, $client, $db, $pwd, $cart_user);
        if(count(array_filter($erpAddressArray)) == 3 AND $erpAddressArray[1] > 0 AND $erpAddressArray[2] > 0){
            $odoo_order_id = 0;
            $partner_id = $erpAddressArray[0];
            $partner_invoice_id = $erpAddressArray[1];
            $partner_shipping_id = $erpAddressArray[2];
            $erp_carrier_id = false;
            $odoo_order_name = '';
            //get carrier code for erp is shipping exists
            if($This_order['shipping_code']){
                $this->load->model('catalog/erp_carrier');
                $erp_carrier = $this->model_catalog_erp_carrier->check_specific_carrier($This_order['shipping_code'], $userId, $client, $db, $pwd);
                $erp_carrier_id  = $erp_carrier['erp_id'];
            }
            $order_array =  array(
                'partner_id' => (int)$partner_id,
                'partner_invoice_id' => (int)$partner_invoice_id,
                'partner_shipping_id' => (int)$partner_shipping_id,
                'pricelist_id' => (int)$pricelist_id,
                'date_order' => $This_order['date_added'],
                'ecommerce_order_id' => (int)$order_id,
                'carrier_id' => (int)$erp_carrier_id,
                'ecommerce_channel' => 'opencart',
                'origin' => $order_id
            );
            $resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton', 'create_order', [[$order_array]], $userId, $client, $db, $pwd, $needContext=true);
            if ($resp[0]==0) {
                return array(0,0,'error while syncing order!!!');
            } else {
                if (isset($resp[1]['status']) AND $resp[1]['status']) {
                    $odoo_order_id = (int)$resp[1]["order_id"];
                    $odoo_order_name = (int)$resp[1]["order_name"];
                } else {
                    return array(0,0,$resp[1]['status_message']);
                }
            }

            //make shipping ,payment, store array for get tax
            $shipping_address = array(
                'country_id' => $This_order['shipping_country_id'],
                'zone_id'    => $This_order['shipping_zone_id']
            );

            $payment_address = array(
                'country_id' => $This_order['payment_country_id'],
                'zone_id'    => $This_order['payment_zone_id']
            );

            $store_address = array(
                'country_id' => $this->config->get('config_country_id'),
                'zone_id'    => $this->config->get('config_zone_id')
            );

            $line_ids = '';
            $This_order_products = $this->model_sale_order->getOrderProducts($order_id);
            foreach ($This_order_products as $key => $value) {
                $This_order_products[$key]['options'] = $this->model_sale_order->getOrderOptions($order_id, $value['order_product_id']);
            }
            $currency_code = $This_order['currency_code'];
            $config_currency_code = $this->config->get('config_currency');
            foreach($This_order_products as $itm){
                $product_option_id = 0;
                $erp_tax_array = array();
                $item_desc = $itm['name'];

                if ($itm['options']) {
                    $product_option_id = $itm['options'][0]['product_option_value_id'];
                }
                $ItemBasePrice = $this->currency->convert($itm['price'], $config_currency_code, $currency_code);

                $context = array(
                    'db'  => $db,
                    'pwd' => $pwd,
                    'cart_user'  => $cart_user,
                    'lang_id' => $this->config->get("config_language_id"),
                    'wkproducttype'=>$wkproducttype,
                );
                //load product model and get product info
                $this->load->model('catalog/erp_product');

                //load tax model and get tax info
                $this->load->model('catalog/erp_tax');

                $product_response = $this->model_catalog_erp_product->check_specific_product($itm['product_id'], $product_option_id, $userId, $client, $context);

                if(!$product_response){
                    return array(0,0,'error product specific sync');
                }
                $erp_product_id = $product_response['erp_id'];
                //add product options details in product description
                foreach($itm['options'] as $value){
                    $item_desc = $item_desc .' name - ' . $value['name'] .' , value - '. $value['value'];
                }

                //get tax from function
                $tax_class_id = $this->db->query("SELECT tax_class_id FROM ".DB_PREFIX."product WHERE product_id = '".$itm['product_id']."'")->row;

                // Enhanced tax debugging for OpenCart
                error_log("=== OPENCART TAX DEBUG ===");
                error_log("Product ID: " . $itm['product_id'] . ", Tax Class: " . json_encode($tax_class_id));
                error_log("Payment Address: " . json_encode($payment_address));
                error_log("Customer Group: " . $This_order['customer_group_id']);

                $tax_per_product = array(); // Initialize to avoid undefined variable
                if($tax_class_id && !empty($tax_class_id['tax_class_id'])) {
                    $tax_per_product = $this->getRates($ItemBasePrice, $tax_class_id['tax_class_id'], $This_order['customer_group_id'],$shipping_address,$payment_address,$store_address);
                    error_log("Tax rates calculated: " . json_encode($tax_per_product));
                } else {
                    error_log("No tax class found for product " . $itm['product_id']);
                }

                foreach ($tax_per_product as $key => $value) {
                    error_log("Processing tax rate ID: $key, checking mapping to ERP...");
                    $erp_tax_id = $this->model_catalog_erp_tax->check_specific_tax($key, $client, $userId, $db, $pwd);
                    error_log("ERP tax ID for rate $key: " . ($erp_tax_id ? $erp_tax_id : 'NOT FOUND'));
                    if($erp_tax_id)
                        $erp_tax_array[] = (int)$erp_tax_id;
                }
                error_log("Final ERP tax array: " . json_encode($erp_tax_array));
                $Order_line_array =  array(
                    'order_id'=>$odoo_order_id,
                    'product_id'=>(int)$erp_product_id,
                    'price_unit'=>$ItemBasePrice,
                    'product_uom_qty'=>$itm['quantity'],
                    'name'=> $item_desc,
                    'tax_id'=>$erp_tax_array,
                );
                $line_resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton', 'create_sale_order_line', [[$Order_line_array]], $userId, $client, $db, $pwd, $needContext=true);
            
                if ($line_resp[0]==0){
                    continue;
                }
                $line_id = (int)$line_resp[1]["order_line_id"];
                $line_ids .= $line_id.",";
            }

            /******************** For Voucher ******************/
            $voucher_code = $this->model_sale_order->getOrderVouchers($order_id);
            if ($voucher_code){
                if(!$voucher_code['code'])
                    $code = "Discount";
                else
                    $code = $code['code'];
                $code = html_entity_decode($code);
                $voucher_line_array =  array(
                    'order_id'=>$odoo_order_id,
                    'name'=> 'Discount',
                    'price_unit'=>$voucher_code['amount'],
                    'description'=>$code,
                    'ecommerce_channel'=>'opencart',
                );
                $line_resp_voucher = $this->model_catalog_connection->callOdooRpc('wk.skeleton', 'create_order_shipping_and_voucher_line', [[$voucher_line_array]], $userId, $client, $db, $pwd, $needContext=true);
                if ($line_resp_voucher[0]==0) {
                    $error = "Order Id ".$order_id." Error While syncing voucher!!!";
                } else {
                    $voucher_line_id =(int)$line_resp_voucher[1]["order_line_id"];
                    $line_ids .= $voucher_line_id.",";
                }
            }

            /******************** For Coupon ******************/
            $coupon_info = $this->db->query("SELECT ch.amount,c.code FROM ".DB_PREFIX."coupon_history ch LEFT JOIN " . DB_PREFIX . "coupon c ON (ch.coupon_id = c.coupon_id) WHERE ch.order_id = '".$order_id."'")->row;
            if ($coupon_info) {
                $coupon_code = $coupon_info['code'];
                if($coupon_code)
                    $code = "Discount";
                else
                    $code = $this->session->data['coupon'];

                $code = html_entity_decode($code);
                $coupon_line_array =  array(
                    'order_id'=> $odoo_order_id,
                    'name'=> 'Voucher',
                    'description'=> $code,
                    'price_unit'=> $coupon_info['amount'],
                    'ecommerce_channel'=>'opencart',
                );
                $line_resp_coupon = $this->model_catalog_connection->callOdooRpc('wk.skeleton',
                    'create_order_shipping_and_voucher_line', [[$coupon_line_array]], $userId, $client, $db, $pwd, $needContext=true);
                if ($line_resp_coupon[0]==0) {
                    $error = "Order Id ".$order_id." Error While syncing coupon info";
                } else {
                    $coupon_line_id = $line_resp_coupon[1]["order_line_id"];
                    $line_ids .= $coupon_line_id.",";
                }
            }

            //for shipping and tax
            $order_total = $this->model_sale_order->getOrderTotals($order_id);

            /******************** For Shipping ******************/
            if ($This_order['shipping_firstname']) {
                $shipping_cost = 0.0;
                $erp_tax_array = array();
                $shipping_description = html_entity_decode($This_order['shipping_method']);
                if($order_total){
                    foreach($order_total as $value){
                        if($value['code']=='shipping'){
                            $order_shipping_title = $value['title'];
                            $shipping_cost = $this->currency->convert($value['value'], $config_currency_code, $currency_code);
                        }
                    }
                }
                $shipping_code_array = explode('.',$This_order['shipping_code']);
                $shipping_code = $shipping_code_array[0];
                $shipping_tax_class_id = $this->config->get('shipping_'.$shipping_code.'_tax_class_id');
                
                // Enhanced shipping tax debugging
                error_log("=== SHIPPING TAX DEBUG ===");
                error_log("Shipping code: $shipping_code, Tax class ID: $shipping_tax_class_id");
                error_log("Shipping cost: $shipping_cost");
                
                if($shipping_tax_class_id){
                    $shipping_tax = $this->getRates($shipping_cost, $shipping_tax_class_id, $This_order['customer_group_id'], $shipping_address, $payment_address, $store_address);
                    error_log("Shipping tax rates: " . json_encode($shipping_tax));
                    
                    foreach ($shipping_tax as $key => $value) {
                       error_log("Processing shipping tax rate ID: $key");
                       $erp_tax_id = $this->model_catalog_erp_tax->check_specific_tax($key, $client, $userId, $db, $pwd);
                       error_log("Shipping ERP tax ID for rate $key: " . ($erp_tax_id ? $erp_tax_id : 'NOT FOUND'));
                        if($erp_tax_id)
                            $erp_tax_array[] = (int)$erp_tax_id;
                    }
                } else {
                    error_log("No shipping tax class configured for method: $shipping_code");
                }
                error_log("Final shipping tax array: " . json_encode($erp_tax_array));
                $shipping_line_array = array(
                    'order_id'=>$odoo_order_id,
                    'name'=> 'Shipping',
                    'is_delivery' =>  true,
                    'price_unit'=> $shipping_cost,
                    'tax_id'=> $erp_tax_array,
                    'description'=> $shipping_description,
                    'ecommerce_channel'=>'opencart',
                );
                $line_resp_shipping = $this->model_catalog_connection->callOdooRpc('wk.skeleton' ,
                    'create_order_shipping_and_voucher_line', [[$shipping_line_array]], $userId, $client, $db, $pwd, $needContext = true);
                if ($line_resp_shipping[0]==0){
                    $error = "Order Id ".$order_id." Error While syncing shipping";
                }else{
                    $shipping_line_id = (int)$line_resp_shipping[1]["order_line_id"];
                    $line_ids .= $shipping_line_id.",";
                }
            }

            $this->db->query("INSERT INTO ".DB_PREFIX."erp_order_merge SET erp_order_id='$odoo_order_id',opencart_order_id='$order_id',created_by='$cart_user',customer_id = '".$partner_id."'");

            $this->addErpOrderHistory($order_id, $odoo_order_id, $partner_id, $This_order['order_status_id'], $userId, $client, $db, $pwd);

            return array($order_id, $partner_id);
        }
        return array(0,0,"error occured during creating an Odoo customer.".''.$erpAddressArray[0]);
    }

    public function getErpOrderAddresses($This_order, $userId, $client, $db, $pwd, $cart_user = 'cart user'){
        $partner_id = 0;
        $partner_shipping_id = 0;
        $partner_invoice_id = 0;

        $erp_country_id = false;
        $erp_state_id   = false;

        $s = $p = array();

        if ($This_order['shipping_country_id']) {
            $s = array(
                'firstname' => $This_order['shipping_firstname'],
                'lastname' => $This_order['shipping_lastname'],
                'address_1' => $This_order['shipping_address_1'],
                'address_2' => $This_order['shipping_address_2'],
                'email' => $This_order['email'],
                'telephone' => $This_order['telephone'],
                'zip' => $This_order['shipping_postcode'],
                'city' => $This_order['shipping_city'],
                'country_id' => $This_order['shipping_country_id'],
                'state_id' => $This_order['shipping_zone_id'],
                'customer_id' => $This_order['customer_id'],
            );
        }

        if ($This_order['payment_zone_id']) {
            $p = array(
                'firstname' => $This_order['payment_firstname'],
                'lastname' => $This_order['payment_lastname'],
                'address_1' => $This_order['payment_address_1'],
                'address_2' => $This_order['payment_address_2'],
                'email' => $This_order['email'],
                'telephone' => $This_order['telephone'],
                'zip' => $This_order['payment_postcode'],
                'city' => $This_order['payment_city'],
                'country_id' => $This_order['payment_country_id'],
                'state_id' => $This_order['payment_zone_id'],
                'customer_id' => $This_order['customer_id'],
            );
        }

        //if shipping is not added than shipping = payment
        
        if(!$s)
            $s = $p;
        // if customer is guest
        
        $isDifferent = $this->checkAddresses($s, $p);

        if(!$This_order['customer_id']){
            $customer_arr =  array(
                'customer_rank'=>1,
                'name'=> html_entity_decode($This_order['firstname'].' '.$This_order['lastname']),
                'email'=>html_entity_decode($This_order['email']),
            );

            $erp_customer_id = $this->AddGuestCustomerToErp($customer_arr, $userId, $client, $db, $pwd, $cart_user);

            if (isset($erp_customer_id['error_message'])) {
                return array($erp_customer_id['error_message']);
            }

            $partner_id = $erp_customer_id['erp_id'];

            if ($isDifferent == true) {
                $partner_shipping_id = $this->createErpAddress($s, $partner_id, $userId, $client, $db, $pwd, $cart_user);
            }
            $partner_invoice_id = $this->createErpAddress($p, $partner_id, $userId, $client, $db, $pwd, $cart_user );
        }

        // if customer is login
        if ($This_order['customer_id'] > 0) {
            //load opencart order model and get order info
            $this->load->model('catalog/erp_customer');

            $partner_id = $this->model_catalog_erp_customer->check_specific_customer($This_order['customer_id'], $client, $userId, $cart_user='Front End', $db, $pwd);
            if(!$partner_id)
                return array('Error customer chk specific');
            $isDifferent = $this->checkAddresses($s, $p);
            if ($isDifferent == true) {
                $shipping_address_id = $this->getAddressId($s);
                if ($shipping_address_id) {
                    $partner_shipping_id = $this->model_catalog_erp_customer->check_specific_address($shipping_address_id, $This_order['customer_id'], $userId, $client, $db, $pwd);
				}
				//Added Feature
				else
					$partner_shipping_id = $this->createErpAddress($s, $partner_id, $userId, $client, $db, $pwd, $cart_user);
            }
            $invoice_address_id = $this->getAddressId($p);
            if ($invoice_address_id) {
                $partner_invoice_id = $this->model_catalog_erp_customer->check_specific_address($invoice_address_id, $This_order['customer_id'], $userId, $client, $db, $pwd);
			}
			//Added feature
			else
            	$partner_invoice_id = $this->createErpAddress($p, $partner_id, $userId, $client, $db, $pwd, $cart_user);
        }

        if($partner_invoice_id > 0 AND $partner_shipping_id > 0)
            return array($partner_id, $partner_invoice_id, $partner_shipping_id);
        else
            return array($partner_id, $partner_invoice_id, $partner_invoice_id);
    }

    public function checkAddresses($shipping, $payment){
        $flag = false;

        if($shipping['state_id'] and $payment['state_id'] ){
            $s = '';
            $b = '';

            if ($shipping[$s.'firstname'] != $payment[$b.'firstname'])
                $flag = true;
            if ($shipping[$s.'lastname'] != $payment[$b.'lastname'])
                $flag = true;
            if ($shipping[$s.'address_1'] != $payment[$b.'address_1'])
                $flag = true;
            if ($shipping[$s.'state_id'] != $payment[$b.'state_id'])
                $flag = true;
            if ($shipping[$s.'country_id'] != $payment[$b.'country_id'])
                $flag = true;
            if ($shipping[$s.'city'] != $payment[$b.'city'])
                $flag = true;
            if ($shipping[$s.'zip'] != $payment[$b.'zip'])
                $flag = true;
        }
        return $flag;
    }

    public function getAddressId($data){
        $address_id = $this->db->query("SELECT address_id FROM ".DB_PREFIX."address WHERE firstname = '".$this->db->escape($data['firstname'])."' AND lastname = '". $this->db->escape($data['lastname'])."' AND address_1 = '".$this->db->escape($data['address_1'])."' AND address_2 = '".$this->db->escape($data['address_2'])."' AND  postcode = '".$data['zip']."' AND city = '".$this->db->escape($data['city'])."' AND country_id = '".$data['country_id']."' AND zone_id = '".$data['state_id']."' AND customer_id = '".$data['customer_id']."'")->row;
        if ($address_id)
            return $address_id['address_id'];
        else
            false;
    }

    //used to add guest customer to erp (nik added)
    public function AddGuestCustomerToErp($key, $userId, $client, $db, $pwd, $cart_user){
        $context =['res_partner_search_mode' => 'customer'];
        $res_resp = $this->model_catalog_connection->callOdooRpc('res.partner',
            'create', [[$key]], $userId, $client, $db, $pwd, $context);
        if (!$res_resp[0]==0) {
            return array(
                'erp_id' => $res_resp[1],
            );
        } else {
            return array(
                'error_message' => 'No epr_id Returned',
            );
        }
    }

    public function createErpAddress($data, $partner_id, $userId, $client, $db, $pwd, $cart_user ){

         // load another model functions
        $this->load->model('catalog/erp_country');
        // load another model functions
        $this->load->model('catalog/erp_state');
        
        // Enhanced address creation debugging
        error_log("=== ADDRESS CREATION DEBUG ===");
        error_log("Creating address for: " . $data['firstname'] . " " . $data['lastname']);
        error_log("OpenCart country_id: " . $data['country_id']);
        error_log("OpenCart state_id: " . $data['state_id']);
        
        $country_iso_code = $this->model_catalog_erp_country->get_iso($data['country_id']);
        $state_dtls   = $this->model_catalog_erp_state->get_state_dtls($data['state_id']);
        
        // Use a simpler approach - let Odoo find the country by ISO code
        error_log("Country ISO: $country_iso_code");
        error_log("State name: " . ($state_dtls['name'] ?? 'No state'));
        
        // First try to create with country_code and let Odoo handle it
        $key = array(
            'parent_id' => (int)$partner_id,
            'name' => $data['firstname'] . ' ' . $data['lastname'],
            'email' => $data['email'],
            'street' => $data['address_1'],
            'street2' => $data['address_2'],
            'phone' => $data['telephone'],
            'zip' => $data['zip'],
            'city' => $data['city'],
        );
        
        // Add country information - try multiple methods
        if (!empty($country_iso_code)) {
            // Method 1: Try with country code (ISO)
            $key['country_code'] = $country_iso_code;
            error_log("Using country_code: $country_iso_code");
        }
        
        // Method 2: Also try to find country by name for backup
        $country_name = $this->getCountryNameById($data['country_id']);
        if (!empty($country_name)) {
            // This can help Odoo match the country
            $key['country_name'] = $country_name;
            error_log("Using country_name: $country_name");
        }
        
        // Add state information if available
        if (!empty($state_dtls['name'])) {
            $key['state_name'] = $state_dtls['name'];
        }
        
        error_log("Final address data for Odoo: " . json_encode($key));
        $res_resp = $this->model_catalog_connection->callOdooRpc('res.partner',
            'create', [[$key]], $userId, $client, $db, $pwd, $needContext = true);
        if (!$res_resp[0]==0) {
            return (int) $res_resp[1];
        } else {
            return 'No Customer Created At Odoo end';
        }
    }

    // Helper method to get country name by ID
    private function getCountryNameById($country_id) {
        try {
            $country_query = $this->db->query("SELECT name FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "'");
            if ($country_query->num_rows > 0) {
                return $country_query->row['name'];
            }
        } catch (Exception $e) {
            error_log("Error getting country name: " . $e->getMessage());
        }
        return '';
    }

    public function getRates($value, $tax_class_id, $customer_group_id, $shipping_address, $payment_address, $store_address) {
        $tax_rates = array();
        if (!$customer_group_id) {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        // Enhanced tax rate debugging
        error_log("getRates called with: value=$value, tax_class_id=$tax_class_id, customer_group_id=$customer_group_id");
        error_log("Payment address (PRIORITY): " . json_encode($payment_address));
        error_log("Shipping address: " . json_encode($shipping_address));

        // PRIORITY 1: Payment address (invoice address) - Most important for tax determination
        if ($payment_address && !empty($payment_address['country_id'])) {
            error_log("Checking payment-based taxes for country: " . $payment_address['country_id']);
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'payment' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$payment_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$payment_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            error_log("Payment tax query found " . count($tax_query->rows) . " tax rates");
            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
                error_log("Found payment tax: " . $result['name'] . " (ID: " . $result['tax_rate_id'] . ", Rate: " . $result['rate'] . "%)");
            }
        }

        // PRIORITY 2: Shipping address - Secondary priority
        if ($shipping_address && !empty($shipping_address['country_id'])) {
            error_log("Checking shipping-based taxes for country: " . $shipping_address['country_id']);
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'shipping' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$shipping_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$shipping_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            error_log("Shipping tax query found " . count($tax_query->rows) . " tax rates");
            foreach ($tax_query->rows as $result) {
                if (!isset($tax_rates[$result['tax_rate_id']])) { // Don't override payment-based taxes
                    $tax_rates[$result['tax_rate_id']] = array(
                        'tax_rate_id' => $result['tax_rate_id'],
                        'name'        => $result['name'],
                        'rate'        => $result['rate'],
                        'type'        => $result['type'],
                        'priority'    => $result['priority']
                    );
                    error_log("Found shipping tax: " . $result['name'] . " (ID: " . $result['tax_rate_id'] . ", Rate: " . $result['rate'] . "%)");
                }
            }
        }

        // Keep the original payment address logic for backward compatibility
        if ($payment_address) {
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'payment' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$payment_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$payment_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
            }
        }

        if ($store_address) {
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'store' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$store_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$store_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
            }
        }

        $tax_rate_data = array();

        foreach ($tax_rates as $tax_rate) {
            if (isset($tax_rate_data[$tax_rate['tax_rate_id']])) {
                $amount = $tax_rate_data[$tax_rate['tax_rate_id']]['amount'];
            } else {
                $amount = 0;
            }

            if ($tax_rate['type'] == 'F') {
                $amount += $tax_rate['rate'];
            } elseif ($tax_rate['type'] == 'P') {
                $amount += ($value / 100 * $tax_rate['rate']);
            }

            $tax_rate_data[$tax_rate['tax_rate_id']] = array(
                'tax_rate_id' => $tax_rate['tax_rate_id'],
                'name'        => $tax_rate['name'],
                'rate'        => $tax_rate['rate'],
                'type'        => $tax_rate['type'],
                'amount'      => $amount
            );
        }

        return $tax_rate_data;
    }

    public function addErpOrderHistory($oc_order_id, $erp_order_id, $erp_customer_id, $oc_status_id, $userId, $client, $db, $pwd){
        $this->load->model('catalog/connection');
        $status_details = $this->db->query("SELECT erp_order_status_id from `" . DB_PREFIX . "erp_order_status_merge` WHERE opencart_order_status_id = '$oc_status_id' ")->row;
        if ($status_details) {
            $erp_status = $status_details['erp_order_status_id'];
            if ($erp_status == 'manual') {
                $this->confirmOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
            }
            if ($erp_status == 'cancel') {
                $this->cancelOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
            }
            if ($erp_status == 'delivered') {
                $this->deliverOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
            }
            if ($erp_status == 'paid') {
                $this->load->model('sale/order');
                $This_order = $this->model_sale_order->getOrder($oc_order_id);
                $this->load->model('catalog/erp_payment');
                $erp_payment_id = $this->model_catalog_erp_payment->check_specific_payment_method($This_order['payment_method'], $This_order['payment_code'], $userId, $client, $db, $pwd);
                $this->makepaidOdooOrder($erp_order_id, $erp_payment_id, $userId, $client, $db, $pwd);
            }
            if ($erp_status == 'done') {
                $this->deliverOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
                $this->load->model('sale/order');
                $This_order = $this->model_sale_order->getOrder($oc_order_id);
                $this->load->model('catalog/erp_payment');
                $erp_payment_id = $this->model_catalog_erp_payment->check_specific_payment_method($This_order['payment_method'], $This_order['payment_code'], $userId, $client, $db, $pwd);
                $this->makepaidOdooOrder($erp_order_id, $erp_payment_id, $userId, $client, $db, $pwd);
            }
            if ($erp_status == 'invoiced') {
                $this->doErpOrderInvoice($erp_order_id, $userId, $client, $db, $pwd);
            }
            return true;
        }
        return;
    }

    public function confirmOdooOrder($erp_order_id, $userId, $client, $db, $pwd) {
        $resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton',
            'confirm_odoo_order', [[(int)$erp_order_id]], $userId, $client, $db, $pwd, $needContext = true);  
  	}
    
    public function deliverOdooOrder($erp_order_id, $userId, $client, $db, $pwd) {
        $resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton',
            'set_order_shipped', [[(int)$erp_order_id]], $userId, $client, $db, $pwd, $needContext = true);
        $log = new oob_log();
        if($resp[1]['status'])
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Shipped Odoo Order ='.$erp_order_id);
        else
         $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
            'Critical in deliver'.$erp_order_id);
    }
    
    public function cancelOdooOrder($erp_order_id, $userId, $client, $db, $pwd) {
        $resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton' ,
            'set_order_cancel', [[(int)$erp_order_id]], $userId, $client, $db, $pwd, $needContext = true); 
        $log = new oob_log();
        if($resp[1]['status'])
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Cancel Odoo Order ='.$erp_order_id);
        else
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Critical in Shipped'.$erp_order_id);
    }

    public function doErpOrderInvoice($erpOrderId, $userId, $client, $db, $pwd) {
        $resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton',
            'create_order_invoice', [(int)$erp_order_id, 'Opencart'], $userId, $client, $db, $pwd, $needContext = true); 
        $log = new oob_log();
        if($resp[1]['status'])
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Invoice Created For Odoo Order='.$erpOrderId);
        else
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Critical In Invoice Created'.$erpOrderId);
    }

    public function makepaidOdooOrder($erpOrderId, $erp_payment_id, $userId, $client, $db, $pwd) {
        $Order_payment_arr = array(
            'journal_id'    =>(int)$erp_payment_id,
            'order_id'=>(int)$erpOrderId,
        );
        $resp = $this->model_catalog_connection->callOdooRpc('wk.skeleton',
        'set_order_paid', [[$Order_payment_arr]], $userId, $client, $db, $pwd, $needContext = true); 
        $log = new oob_log();
        if($resp[1]['status'])
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Invoice Set to paid='.$erpOrderId);
        else
            $log->logMessage(__FILE__,__LINE__,$resp[1]['status_message'],
                'Critical In Invoice paid'.$erpOrderId);
        }
}
?>
