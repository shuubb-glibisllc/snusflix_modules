<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpProductOptions extends Model {

    public function getErpProductArray(){

    }

    public function check_all_options($userId, $client, $context){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';

        $data = $this->db->query("SELECT a.`option_id`,a.`sort_order`,a.`type`, d.`name`, erp.`is_synch`, erp.`erp_option_id` FROM `". DB_PREFIX ."option` a LEFT JOIN `".DB_PREFIX."option_description` d ON (a.`option_id` = d.option_id )
        LEFT JOIN `".DB_PREFIX."erp_product_option_merge` erp ON (erp.`opencart_option_id` = a.option_id)
        WHERE  erp.`is_synch` IS NULL or erp.`is_synch` =1")->rows;

        if (count($data) == 0) {
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($data as $option_data){
            $option_name = str_replace('+',' ',html_entity_decode($option_data['name']));
            $key     = array(
                'name' => $option_name,
            );
            if ($option_data['is_synch']==NULL) {
                $create = $this->create_product_option($key, $option_data['option_id'], $userId, $client, $context);
                if (!$create['erp_id'] > 0) {
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $option_data['option_id'] . ',';
                }
            } else {
                $update = $this->update_product_option($key, $option_data['erp_option_id'], $option_data['option_id'], $userId, $client, $context);
            }
        }

        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => 1,
            'ids' => $ids
        );
    }

    public function create_product_option($key, $option_id, $userId, $client, $context){

        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.attribute', 'create', [[$key]], $userId, $client, $context['db'], $context['pwd'], $needContext=false);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'erp_id' => -1
            );
        } else {
            $erp_id = $resp[1];
            $cart_user = $context['cart_user'];
            $this->addto_option_merge($option_id, $erp_id, $cart_user);
            $this->addOptionOdooMerge($erp_id, $option_id, $userId, $client, $context['db'], $context['pwd']);
            return array(
                'erp_id' => $erp_id
            );
        }
    }

    public function update_product_option($key, $erp_option_id, $id_option, $userId, $client, $context)
    {
        $vals =[[(int)$erp_option_id], $key];
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.attribute', 'write', [$vals], $userId, $client, $context['db'], $context['pwd'], $needContext=true);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'value' => False
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_option_merge` set `is_synch`=0 where `opencart_option_id`='$id_option'");
        }
    }

    public function addto_option_merge($oc_option_id, $erp_option_id, $oc_user = 'Front End'){
        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_merge` SET erp_option_id = '$erp_option_id' , opencart_option_id = '$oc_option_id' ,created_by = '$oc_user' ");
    }

    public function addOptionOdooMerge($erp_id, $option_id, $userId, $client, $db, $pwd)
    {
        $data = array(
            'odoo_id' =>(int)$erp_id,
            'ecomm_id' => $option_id,
            'need_sync' => 'No',
            'name' => (int)$erp_id,
            'ecomm_attribute_code' => '-',
            'created_by'=> 'opencart'
        );
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.attribute.mapping', 'create', [[$data]], $userId, $client, $db, $pwd, $needcontext = true);
    }

    public function check_all_option_values($userId, $client, $context){
        $db = $context['db'];
        $pwd = $context['pwd'];
        $cart_user = $context['cart_user'];
        $id_lang = $context['id_lang'];
        $is_error      = 0;
        $error_message = '';
        $ids           = '';

        $data = $this->db->query("SELECT a.`option_id`,a.`option_value_id`, d.`name`, erp.`is_synch`, erp.`erp_option_value_id` FROM `". DB_PREFIX ."option_value` a LEFT JOIN `".DB_PREFIX."option_value_description` d ON (a.`option_value_id` = d.option_value_id and d.`language_id`= ".$id_lang." )
        LEFT JOIN `".DB_PREFIX."erp_product_option_value_merge` erp ON (erp.`opencart_option_value_id` = a.option_value_id)
        WHERE  erp.`is_synch` IS NULL or erp.`is_synch` =1")->rows;

        if (count($data) == 0) {
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($data as $value_data) {
            $erp_option_id = $this->check_specific_options($userId, $client, $value_data['option_id'], $context);
            if ($erp_option_id) {
                $value_name = str_replace('+',' ',html_entity_decode($value_data['name']));
                $key     = array(
                    'name' => $value_name,
                    'attribute_id' => (int)$erp_option_id,
                );
                if ($value_data['is_synch']==NULL) {
                    $create = $this->create_product_option_value($key, $value_data['option_value_id'], $value_data['option_id'], $userId, $client, $context);
                    if (!$create['erp_id'] > 0){
                        $is_error = 1;
                        $error_message .= $create['error_message'] . ',';
                        $ids .= $value_data['option_id'] . ',';
                    }
                } else {
                    $update = $this->update_product_option_value($key, $value_data['option_value_id'], $value_data['erp_option_value_id'], $userId, $client, $context);
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

    public function check_specific_options($userId, $client, $option_id, $context){
        $id_lang = $context['id_lang'];
        $erp_id = false;
        $erp_option_id = $this->check_product_options($option_id);
        if ($erp_option_id[0] > 0)
            $erp_id = $erp_option_id[0];
        else {
            $data = $this->db->query("SELECT a.`option_id`,a.`sort_order`,a.`type`, d.`name`, erp.`is_synch`, erp.`erp_option_id` FROM `". DB_PREFIX ."option` a LEFT JOIN `".DB_PREFIX."option_description` d ON (a.`option_id` = d.option_id )
            LEFT JOIN `".DB_PREFIX."erp_product_option_merge` erp ON (erp.`opencart_option_id` = a.option_id)
            WHERE  a.`option_id`=".$option_id."")->row;

            $key     = array(
                'name' => $data['name'],
            );

            if ($data['is_synch']==NULL) {
                $create = $this->create_product_option($key, $data['option_id'], $userId, $client, $context);
                $erp_id = $create['erp_id'];
            } else {
                $erp_id = $data['erp_option_id'];
                $update = $this->update_product_option($key, $data['erp_option_id'], $data['option_id'],  $userId, $client, $context);
            }
        }

        return $erp_id;
    }

    public function check_specific_options_value($userId, $client, $option_value_id, $context){
        $id_lang = $context['id_lang'];
        $erp_id = false;
        $erp_value_id = $this->check_option_value($option_value_id);
        if ($erp_value_id[0] > 0)
            $erp_id = $erp_value_id[0];
        else {
            $data = $this->db->query("SELECT d.`option_id`,d.`option_value_id`, d.`name`, erp.`is_synch`, erp.`erp_option_value_id` FROM `". DB_PREFIX ."option_value_description` d
            LEFT JOIN `".DB_PREFIX."erp_product_option_value_merge` erp ON (erp.`opencart_option_value_id` = d.option_value_id)
            WHERE  d.`option_value_id`=".$option_value_id." and d.`language_id`= ".$id_lang."")->row;

           $erp_option_id = $this->check_specific_options($userId, $client, $data['option_id'], $context);
           if ($erp_option_id) {
                $value_name = str_replace('+',' ',html_entity_decode($data['name']));
                $key     = array(
                    'name' => $value_name,
                    'attribute_id' => (int)$erp_option_id,
                );
                if ($data['is_synch']==NULL) {
                    $create = $this->create_product_option_value($key, $data['option_value_id'], $data['option_id'], $userId, $client, $context);
                    $erp_id = $create['erp_id'];
                } else {
                    $update = $this->update_product_option_value($key, $data['option_value_id'], $data['erp_option_value_id'], $userId, $client, $context);
                    $erp_id = $data['erp_option_value_id'];
                }
            }
        }
        return $erp_id;
    }

    public function create_product_option_value($key, $oc_value_id, $oc_option_id, $userId, $client, $context){
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.attribute.value', 'create', [[$key]], $userId, $client, $context['db'], $context['pwd'], $needContext=false);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'erp_id' => -1
            );
        } else {
            $erp_id = $resp[1];
            $cart_user = $context['cart_user'];
            $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_value_merge` SET erp_option_value_id = '$erp_id' , opencart_option_value_id = '$oc_value_id' ,option_id = '$oc_option_id' ");
            $this->addOptionValueOdooMerge($erp_id, $oc_value_id, $key['attribute_id'], $oc_option_id, $userId, $client, $context['db'], $context['pwd']);
            return array(
                'erp_id' => $erp_id
            );
        }
    }

    public function addOptionValueOdooMerge($erp_id, $oc_value_id, $erp_attr_id, $oc_attr_id, $userId, $client, $db, $pwd)
    {
        $data = array(
            'odoo_id' =>(int)$erp_id,
            'ecomm_id' => $oc_value_id,
            'odoo_attribute_id'=> (int)$erp_attr_id,
            'ecomm_attribute_id' => $oc_attr_id,
            'need_sync' => 'No',
            'name' => (int)$erp_id,
            'created_by'=> 'opencart'
        );
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.option.mapping', 'create', [[$data]], $userId, $client, $db, $pwd, $needcontext = true);
    }

    public function update_product_option_value($key, $option_value_id, $erp_option_value_id, $userId, $client, $context){
        $vals =[[(int)$erp_option_value_id], $key];
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.attribute.value', 'write', [$vals], $userId, $client, $context['db'], $context['pwd'], $needContext=true);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'value' => False
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_option_value_merge` set `is_synch`=0 where `opencart_option_value_id`='$option_value_id'");
            return array(
                'value' => True
            );
        }
    }

    public function check_product_options($id_option){
        $check_erp_id = $this->db->query("SELECT  `is_synch`, `erp_option_id` FROM `". DB_PREFIX ."erp_product_option_merge` where opencart_option_id='".$id_option."'")->row;
        if (!isset($check_erp_id['erp_option_id']))
            return array(
                0
            );

        if ($check_erp_id['erp_option_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1)
                return array(
                    -1,
                    $check_erp_id['erp_option_id']
                );
            else
                return array(
                    $check_erp_id['erp_option_id']
                );
        }
    }

	public function check_option_value($id_option_value){
        $check_erp_id = $this->db->query("SELECT  `is_synch`, `erp_option_value_id` FROM `". DB_PREFIX ."erp_product_option_value_merge` WHERE opencart_option_value_id='".$id_option_value."'")->row;

        if (!isset($check_erp_id['erp_option_value_id']))
            return array(
                0
            );

        if ($check_erp_id['erp_option_value_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1)
                return array(
                    -1,
                    $check_erp_id['erp_option_value_id']
                );
            else
                return array(
                    $check_erp_id['erp_option_value_id']
                );
        } else
            return array(
                0
            );
    }

    public function check_option_for_value($option_id)
    {
        $check = $this->db->query("SELECT  * FROM `". DB_PREFIX ."erp_product_option_merge` WHERE opencart_option_id='".$option_id."'")->row;
        if($check)
            return $check['erp_option_id'];
        return false; 
    }

    public function check_option_value_merge($oc_option_value, $erp_value_id){
        $check = $this->db->query("SELECT  * FROM `". DB_PREFIX ."erp_product_option_value_merge` WHERE opencart_option_value_id='".$oc_option_value."' and erp_option_value_id='".$erp_value_id."' ")->row;
        if($check)
            return false;
        return true;
    }

    public function getErpProductOptionValueArray($userId, $client, $db, $pwd){

        $OptionValues = array();
        $condition=[[]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('product.attribute.value', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($OptionValues, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition=array('fields'=>['id','name']);
            $vals = array([$response1[1]],$condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('product.attribute.value', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($OptionValues, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
                    array_push($OptionValues,
                        array(
                            'id' => $id,
                            'name'=>$name
                        )
                    );
                }
            }
        }
        return $OptionValues;
    }

    public function getErpProductOptionArray($userId, $client, $db, $pwd){

        $Option = array();
        $condition=[[]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('product.attribute', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Option, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition=array('fields'=>['id','name']);
            $vals = array([$response1[1]],$condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('product.attribute', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0] == 0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Option, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
                    array_push($Option,
                        array(
                            'id' => $id,
                            'name'=>$name
                        )
                    );
                }
            }
        }
        return $Option;
    }

	public function check_option_merge($oc_option_value, $erp_value_id){
        $check = $this->db->query("SELECT  * FROM `". DB_PREFIX ."erp_product_option_merge` WHERE opencart_option_id='".$oc_option_value."' and erp_option_id='".$erp_value_id."' ")->row;
        if($check)
            return false;
        return true;
    }

	public function addto_option_value_merge($option_value_id, $erp_option_value_id, $opencart_option_id){

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_value_merge` SET erp_option_value_id = '$erp_option_value_id' , opencart_option_value_id = '$option_value_id' ,option_id = '$opencart_option_id' ");
    }
}
?>
