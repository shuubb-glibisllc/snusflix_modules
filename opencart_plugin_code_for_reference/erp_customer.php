<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpCustomer extends Model {

    //To check for all customers.
    public function check_all_customers($userId, $client, $db, $pwd, $cart_user){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $data          = $this->db->query("SELECT `customer_id`,`telephone`,`firstname`,`lastname`,`email` FROM `" . DB_PREFIX . "customer`  WHERE `customer_id` NOT IN (SELECT `opencart_customer_id` FROM `" . DB_PREFIX . "erp_customer_merge` WHERE `is_synch` = 0)")->rows;
        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }
        foreach ($data as $customer_data) {
            $id_customer = $this->search_customer($customer_data['customer_id']);

            if ($id_customer[0] <= 0) {
                $customer_data['firstname'] = html_entity_decode($customer_data['firstname']);
                $customer_data['lastname']  = html_entity_decode($customer_data['lastname']);
                $key                        = array(
                    'name' => $customer_data['firstname'] .' '. $customer_data['lastname'],
                    'email' => html_entity_decode($customer_data['email']),
                    'customer_rank' => 1,
                    'phone' => $customer_data['telephone'],
                );
                if ($id_customer[0] == 0) {
                    $context =['res_partner_search_mode' => 'customer'];
                    $this->load->model('catalog/connection');      
                    $resp = $this->model_catalog_connection->callOdooRpc('res.partner', 'create', [[$key]], $userId, $client, $db, $pwd, $context);
                    if ($resp[0]) {
                        $db_done = $this->addto_customer_merge($resp[1], $customer_data['customer_id'], $cart_user);
                        $add_to_odoo = $this->addto_openerp_merge($resp[1], $customer_data['customer_id'], '-', $userId, $client, $db, $pwd);
                        if ($add_to_odoo['is_error'] == 1) {
                            $is_error = 1;
                            $error_message .= $add_to_odoo['error_message'] . ',';
                            $ids .= $customer_data['customer_id'] . ',';
                        }
                        if ($db_done) {
                            $create_add = $this->check_all_address($resp[1], $customer_data['customer_id'], $userId, $client, $cart_user, $db, $pwd);
                            if ($create_add['is_error'] == 1) {
                                $is_error      = 1;
                                $error_message = 'Error in Address';
                                $ids .= $create_add['ids'] . ',';
                            }
                        }
                    } else {
                        $db_done   = 0;
                        $is_error  = 1;
                        $error_msg = $resp[1];
                        $error_message .= $error_msg . ',';
                        $ids .= $customer_data['customer_id'] . ',';
                    }
                }

                if ($id_customer[0] == -1) {
                    $update = $this->update_customer($customer_data, $customer_data['customer_id'], $id_customer[1], $userId, $client, $db, $pwd);
                    if ($update['value'] != True) {
                        $is_error = 1;
                        $error_message .= $update['error_message'] . ',';
                        $ids .= $customer_data['customer_id'] . ',';
                    }
                    $create_add = $this->check_all_address($id_customer[1], $customer_data['customer_id'], $userId, $client, $cart_user, $db, $pwd);
                    if ($create_add['is_error'] == 1) {
                        $is_error      = 1;
                        $error_message .= 'Error in Address';
                        $ids .= $create_add['ids'] . ',';
                    }
                }
            }
        }
        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => 1,
            'ids' => $ids
        );
    }

    public function search_customer($customer_id){
        $check = $this->db->query("SELECT `is_synch`,`erp_customer_id`  from `" . DB_PREFIX . "erp_customer_merge` where opencart_customer_id = '$customer_id'")->row;

        if (isset($check['erp_customer_id']) AND $check['erp_customer_id'] > 0) {
            if ($check['is_synch'] == 1) {
                $check_arr[0] = -1;
                $check_arr[1] = $check['erp_customer_id'];
                return $check_arr;
            } else {
                $check_arr[0] = $check['erp_customer_id'];
                return $check_arr;
            }
        } else {
            $check_arr[0] = 0;
            return $check_arr;
        }
    }

    //To insert data in customer merge table
    public function addto_customer_merge($erp_customer_id, $customer_id, $cart_user = 'Front End'){
        $data = array(
            'erp_customer_id' => $erp_customer_id,
            'customer_id' => $customer_id,
            'created_by' => $cart_user
        );

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_customer_merge` SET erp_customer_id = '$erp_customer_id' , opencart_customer_id = '$customer_id' , created_by = '$cart_user', created_on = NOW() ");

        return $this->db->getLastId();
    }

    public function chkErpOpencartCustomers($erp_id, $cart_id){
        $chk  = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_customer_merge` WHERE opencart_customer_id='$cart_id' or erp_customer_id='$erp_id'")->row;
        if($chk)
            return false;
        return true;
    }

    public function addto_openerp_merge($erp_id, $cart_id, $cart_add_id, $userId, $client, $db, $pwd){
        $key = array(
            'odoo_id' => (int)$erp_id,
            'ecomm_id' => $cart_id,
            'name' => (int)$erp_id,
            'created_by' => 'Opencart',
            'ecomm_address_id' => $cart_add_id
        );
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.partner.mapping', 'create', [[$key]], $userId, $client, $db, $pwd, $needContext = true );
        
        if ($resp[0]==0) {
            $error_message = $resp[1];
            return array(
                'error_message' => $error_message,
                'is_error' => 1
            );
        } else {
            return array(
                'is_error' => 0
            );
        }
    }

    public function check_all_address($erp_cust_id, $cart_customer_id, $userId, $client, $cart_user, $db, $pwd){
        $is_error = 0;
        $error_message = '';
        $ids = '';
        $data = $this->db->query("SELECT a.*,c.telephone,c.email from `" . DB_PREFIX . "address` a LEFT JOIN " .DB_PREFIX. "customer c ON (a.customer_id = c.customer_id) where a.customer_id ='$cart_customer_id'")->rows;

        $this->load->model('catalog/erp_country');
        $this->load->model('catalog/erp_state');

        foreach ($data as $address_data) {
            $search_address = $this->search_address($address_data['address_id']);
            if ($search_address[0] <= 0) {
                $country_iso_code = '';
                $state_name = '';
                if ($address_data['country_id'] != null && $address_data['country_id'] != "" && $address_data['country_id'] != 0) {
                    $country_iso_code = $this->model_catalog_erp_country->get_iso($address_data['country_id']);
                }
                if ($address_data['zone_id'] != null && $address_data['zone_id'] != "" && $address_data['zone_id'] != 0) {
                    $state_dtls   = $this->model_catalog_erp_state->get_state_dtls($address_data['zone_id']);
                    $state_name =$state_dtls['name'];
                }
                $key = array(
                    'parent_id' => (int)$erp_cust_id,
                    'name' => html_entity_decode($address_data['firstname'].' '.$address_data['lastname']),
                    'email' => html_entity_decode($address_data['email']),
                    'street' => html_entity_decode($address_data['address_1']),
                    'street2' => html_entity_decode($address_data['address_2']),
                    'phone' => $address_data['telephone'],
                    'zip' => $address_data['postcode'],
                    'city' => html_entity_decode($address_data['city']),
                    'country_code' => $country_iso_code,
                    'region' => $state_name,
                );
                if ($search_address[0] == 0) {
                    $this->load->model('catalog/connection');      
                    $resp = $this->model_catalog_connection->callOdooRpc('res.partner', 'create', [[$key]], $userId, $client, $db, $pwd, $needContext = true);
                    if ($resp[0]) {
                        $this->addto_address_merge($address_data['address_id'], $resp[1], $address_data['customer_id'], $cart_user);
                        $add_to_odoo = $this->addto_openerp_merge($resp[1], $address_data['customer_id'], $address_data['address_id'], $userId, $client, $db, $pwd);
                        if ($add_to_odoo['is_error'] == 1) {
                            $is_error = 1;
                            $error_message .= $add_to_odoo['error_message'] . ',';
                            $ids .= $address_data['customer_id'] . ',';
                        }
                    } else {
                        $is_error  = 1;
                        $error_msg = $resp[1];
                        $error_message .= $error_msg . ',';
                        $ids .= $address_data['customer_id'] . ',';
                    }
                }
                if ($search_address[0] == -1) {
                    $update = $this->update_address($key, $address_data['address_id'], $search_address[1], $userId, $client, $db, $pwd);
                    if ($update['value'] != True) {
                        $is_error = 1;
                        $error_message .= $update['error_message'] . ',';
                        $ids .= $address_data['customer_id'] . ',';
                    }
                }
            }
        }
        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'ids' => $ids
        );
    }

    public function search_address($id_address) {
        $erp_address = $this->db->query("SELECT `is_synch`,`erp_address_id`  from `" . DB_PREFIX . "erp_address_merge` where `opencart_address_id`='$id_address'")->row;
        if (isset($erp_address['erp_address_id']) AND $erp_address['erp_address_id'] > 0) {
            if ($erp_address['is_synch'] == 0) {
                $arr[0] = -1;
                $arr[1] = $erp_address['erp_address_id'];
                return $arr;
            } else {
                $arr[0] = $erp_address['erp_address_id'];
                return $arr;
            }
        } else {
            $arr[0] = 0;
            return $arr;
        }
    }

    public function addto_address_merge($id_address, $erp_address_id, $id_customer, $cart_user = 'Front End'){
        $data = array(
            'erp_address_id' => $erp_address_id,
            'address_id' => $id_address,
            'created_by' => $cart_user,
            'id_customer' => $id_customer
        );

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_address_merge` SET erp_address_id = '$erp_address_id' , opencart_address_id = '$id_address' ,customer_id = '$id_customer', created_by = '$cart_user', created_on = NOW() ");
    }

    public function update_address($key, $id_address, $erp_id, $userId, $client, $db, $pwd){
        $vals =[[(int)$erp_id], $key];
        $this->load->model('catalog/connection');
        
        $resp = $this->model_catalog_connection->callOdooRpc('res.partner', 'write', [$vals], $userId, $client, $db, $pwd, $needContext = true);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'value' => false,
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_address_merge` set `is_synch`=0 where `opencart_address_id`='$id_address'");
            return array(
                'value' => True
            );
        }
    }

    public function update_customer($data, $cart_id, $erp_id, $userId, $client, $db, $pwd){ 
        $key     = array(
            'name' =>  html_entity_decode($data['firstname'] .' '. $data['lastname']),
            'email' => html_entity_decode($data['email']),
            'phone'=> $data['telephone']
        );
        $vals =[[(int)$erp_id], $key];
        $this->load->model('catalog/connection');
        
        $resp = $this->model_catalog_connection->callOdooRpc('res.partner', 'write', [$vals], $userId, $client, $db, $pwd, $needContext = true);
        if ($resp[0]==0) {
            return array(
                'value' => false,
            );
        } else {
            $this->db->query("UPDATE `" . DB_PREFIX . "erp_customer_merge` set `is_synch`= 0 where opencart_customer_id = '$cart_id'");
            return array(
                'value' => True
            );
        }
    }

    public function check_specific_customer($id_customer, $client, $userId, $cart_user='Front End', $db, $pwd){
		$check_customer_id = $this->search_customer($id_customer);
		if ($check_customer_id[0] > 0)
			return $check_customer_id[0];
		elseif($check_customer_id[0]==-1)
			return $check_customer_id[1];
		else {
            $customer_data = $this->db->query("SELECT `customer_id`,`telephone` , `firstname`,`lastname`,`email` FROM `" . DB_PREFIX . "customer`  WHERE `customer_id` = '".$id_customer."'")->row;
            $customer_data['firstname'] = html_entity_decode($customer_data['firstname']);
            $customer_data['lastname']  = html_entity_decode($customer_data['lastname']);
            $key = array(
                'name' => $customer_data['firstname'] .' '. $customer_data['lastname'],
                'email' =>html_entity_decode($customer_data['email']),
                'customer_rank' => 1,
                'phone'=>$customer_data['telephone'],
            );
            $context =['res_partner_search_mode' => 'customer'];
            $this->load->model('catalog/connection');      
            $resp = $this->model_catalog_connection->callOdooRpc('res.partner', 'create', [[$key]], $userId, $client, $db, $pwd, $context);
            if ($resp[0]) {
                $db_done = $this->addto_customer_merge($resp[1], $customer_data['customer_id'], $cart_user);
                $add_to_odoo = $this->addto_openerp_merge($resp[1], $customer_data['customer_id'], '-', $userId, $client, $db, $pwd);
                return $resp[1];
            }
        }
        return -1;
	}

    public function check_specific_address($id_address = false, $id_customer, $userId, $client, $db, $pwd){
		$erp_state_id=False;
		$erp_country_id=False;
        $erp_address = $this->search_address($id_address);
        if ($erp_address[0] > 0 AND $id_address) {
            return $erp_address[0];
        } else {
            $erp_cust_id = $this->check_specific_customer($id_customer, $client, $userId, $cart_user='Front End', $db, $pwd);
            if($id_address)
                $add = "AND a.address_id = '".$id_address."'";
            else
                $add = '';
            $data = $this->db->query("SELECT DISTINCT a.address_id, a.country_id, a.zone_id, a.customer_id, a.company, a.lastname, a.firstname, a.address_1, a.address_2, a.postcode, a.city, cu.telephone, cu.email, c.`name` AS country,c.iso_code_3 AS country_iso, s.name AS state, s.code AS state_iso,
            IFNULL(erp.`is_synch`, 0) AS is_synch, IFNULL(erp.`erp_address_id`, 0) AS erp_address_id
            FROM `".DB_PREFIX."customer` cu
            LEFT JOIN `".DB_PREFIX."address` a ON (a.`customer_id` = cu.`customer_id`)
            LEFT JOIN `".DB_PREFIX."country` c ON (a.`country_id` = c.`country_id`)
            LEFT JOIN `".DB_PREFIX."zone` s ON (s.`zone_id` = a.`zone_id`)
            LEFT JOIN `".DB_PREFIX."erp_address_merge` erp ON (erp.`opencart_address_id` = a.`address_id` AND erp.`customer_id` = a.`customer_id`) WHERE cu.customer_id='".$id_customer."' $add ")->rows;

            $this->load->model('catalog/erp_country');
            $this->load->model('catalog/erp_state');
            foreach ($data as $address_data) {
                
                $search_address = $this->search_address($address_data['address_id']);
                if ($search_address[0] <= 0) {
                    $country_iso_code = '';
                    $state_name='';
                    if ($address_data['country_id'] != null && $address_data['country_id'] != "" && $address_data['country_id'] != 0) {
                        $country_iso_code = $this->model_catalog_erp_country->get_iso($address_data['country_id']);
                    }
                    if ($address_data['zone_id'] != null && $address_data['zone_id'] != "" && $address_data['zone_id'] != 0) {
                        $state_dtls   = $this->model_catalog_erp_state->get_state_dtls($address_data['zone_id']);
                        $state_name =$state_dtls['name'];
                    }
                    $key = array(
                        'parent_id' => (int)$erp_cust_id,
                        'name' => html_entity_decode($address_data['firstname'].' '.$address_data['lastname']),
                        'email' => html_entity_decode($address_data['email']),
                        'street' => html_entity_decode($address_data['address_1']),
                        'street2' => html_entity_decode($address_data['address_2']),
                        'phone' => $address_data['telephone'],
                        'zip' => $address_data['postcode'],
                        'city' => html_entity_decode($address_data['city']),
                        'country_code' => $country_iso_code,
                        'region' => $state_name,
                    );
                    if ($search_address[0] == 0) {
                        $this->load->model('catalog/connection');      
                        $resp = $this->model_catalog_connection->callOdooRpc('res.partner', 'create', [[$key]], $userId, $client, $db, $pwd, $needContext = true);
                        if ($resp[0]) {
                            $this->addto_address_merge($address_data['address_id'], $resp[1], $address_data['customer_id'], $cart_user);
                            $add_to_odoo = $this->addto_openerp_merge($resp[1], $address_data['customer_id'], $address_data['address_id'], $userId, $client, $db, $pwd);
                            if ($add_to_odoo['is_error'] == 1) {
                                $error =  'Error Address not adding - specific address'. $address_data['address_id'].' ,';
                            }
                            return $resp[1];
                        } 
                    }
                    if ($search_address[0] == -1) {
                        $update = $this->update_address($key, $address_data['address_id'], $search_address[1], $userId, $client, $db, $pwd);
                        if ($update['value'] != True) {
                            $error =  'Error Address not updating - specific address'. $address_data['address_id'].' ,';
                        } else {
                            return $search_address[1];
                        }
                    }
                }
            }
        }
    }

    public function getErpCustomerArray($userId, $client, $db, $pwd) {
        $Customer = array();
        $condition=[[['customer_rank','=',1]]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('res.partner', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Customer, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition=array('fields'=>['id','name']);
            $vals = array([$response1[1]],$condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('res.partner', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Customer, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
    
                    array_push($Customer,
                        array(
                            'id' => $id,
                            'name'=>$name
                        )
                    );
                }
            }
        }
        return $Customer;
    }
}
?>
