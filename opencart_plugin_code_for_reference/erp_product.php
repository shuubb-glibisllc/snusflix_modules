<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpProduct extends Model {    
    
    public function get_product_data($id_product, $option_data=false, $id_lang, $userId, $client, $context)
    {
        if ($option_data) {
            $erp_template_id = $this->check_specific_template($id_product, $userId, $client, $context);
            $this->load->model('catalog/erp_product_options');
            $erp_option_value_id = $this->model_catalog_erp_product_options->check_specific_options_value($userId, 
                $client, $option_data['option_value_id'], $context);
            $option_detail=$this->getOptionsDetails($option_data['product_option_value_id']);
            $temp_data = array(
                'ecomm_option_id' => (int)$option_data['product_option_value_id'],
                'ecomm_id' => $id_product,
                'product_tmpl_id' => (int)$erp_template_id,
                'value_ids' => array((int)$erp_option_value_id),
                'price_extra' => $option_detail[0]['price'],
                'product_quantity' => $option_detail[0]['quantity'],
            );
        } else {
            $temp_data = $this->get_template_data($id_product, $id_lang, $userId, $client, $context);
        }
        return $temp_data;
    }
    
    public function create_and_update_product($temp_data, $id_product, $product_option_value_id,
                                                    $product_quantity, $userId, $client, $context) {
        $cart_user = $context['cart_user'];
        $status = $this->search_product($id_product, $product_option_value_id);
        if ($status[0]==0){
            $response_create = $this->create_product($userId, $client, $temp_data, $context);
            if ($response_create['product_id']){
                $erp_product_id = $response_create['product_id'];
                $template_id = $response_create['template_id'];
                $this->addto_product_merge($erp_product_id, $id_product, $cart_user, $product_option_value_id);
                $this->addto_product_template_merge($template_id, $id_product, $cart_user);
                $this->create_product_quantity($userId, $client, $erp_product_id, $product_quantity, $context);
            }
        } elseif ($status[0]==-1) {
            $erp_product_id = $status[1];
            $response_update = $this->update_product($userId, $client, $erp_product_id, $temp_data, $context);
        } else
            $erp_product_id = $status[0];
        return $erp_product_id;
    }

public function check_all_products($userId, $client, $context, $id_product=false) {
    $db = $context['db'];
    $pwd = $context['pwd'];
    $cart_user = $context['cart_user'];
    $id_lang = $context['id_lang'];
    $is_error      = 0;
    $error_message = '';
    $ids           = '';
    
    // Increase limits for large datasets
    set_time_limit(600); // 10 minutes
    ini_set('memory_limit', '1024M'); // 1GB
    
    $offset = 0;
    $batch_size = 500;
    $total_synced = 0;
    
    do {
        $sql_query = "SELECT  p.product_id, p.sku, p.ean, p.quantity FROM ". DB_PREFIX ."product p WHERE p.product_id NOT IN (SELECT opencart_product_id FROM ".DB_PREFIX."erp_product_template_merge WHERE is_synch = 0)";
        
        if($id_product)
            $sql_query.= ' AND p.product_id='.$id_product."";
        
        $sql_query.= ' LIMIT '.$batch_size.' OFFSET '.$offset;
        
        $data = $this->db->query($sql_query)->rows;
        
        if (count($data) == 0) {
            break;
        }

        foreach ($data as $product_data) {
            $check_option = true;
            if ($context['wkproducttype']=='template') {
                $check_option = false;
            }
            if (!$check_option) {
                $temp_data = $this->get_product_data($product_data['product_id'], false, $id_lang, $userId, $client, $context);
                $product_quantity = $product_data['quantity'];
                $erp_product_id = $this->create_and_update_product($temp_data, $product_data['product_id'], 0, $product_quantity, $userId, $client, $context);
            } else {
                $check_option_value_data = $this->db->query("SELECT `product_option_value_id`,`option_id`, `option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_data['product_id']."'")->rows;
                if ($check_option_value_data)  {
                    foreach ($check_option_value_data as $option_data) {
                        $temp_data = $this->get_product_data($product_data['product_id'], $option_data, $id_lang, $userId, $client, $context);
                        $temp_data['default_code'] = $product_data['sku'];
                        $product_quantity = $temp_data['product_quantity'];
                        unset($temp_data['product_quantity']);
                        $erp_product_id = $this->create_and_update_product($temp_data, $product_data['product_id'], $option_data['product_option_value_id'], $product_quantity, $userId, $client, $context);
                    }
                } else {
                    $temp_data = $this->get_product_data($product_data['product_id'], false , $id_lang , $userId, $client, $context);
                    $product_quantity = $product_data['quantity'];
                    $erp_product_id = $this->create_and_update_product($temp_data, $product_data['product_id'], 0, $product_quantity, $userId, $client, $context);
                }
            }
            $total_synced++;
        }
        
        $offset += $batch_size;
        
    } while (count($data) == $batch_size && !$id_product);
    
    return array(
        'is_error' => $is_error,
        'error_message' => $error_message,
        'value' => $total_synced > 0 ? 1 : 0,
        '$ids' => $ids
    );
}
    public function create_product($userId, $client, $arrayVal, $context){
        $needContext = [];
        if(array_key_exists('ecomm_option_id', $arrayVal)) {
            $needContext['ecomm_option_id'] = $arrayVal['ecomm_option_id'];
            unset($arrayVal['ecomm_option_id']);
        }
        $this->load->model('catalog/connection');
        $needContext['create_product_product'] = true;
        $resp = $this->model_catalog_connection->callOdooRpc('product.product', 'create_template_product_dict',
            [[$arrayVal]], $userId, $client, $context['db'], $context['pwd'], $needContext);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'value' => False
            );
        } else {
            return array(
                'product_id' => $resp[1]['product_id'],
                'template_id' => $resp[1]['template_id']
            );
        }
    }

    // Code for update records
    public function update_product($userId, $client, $erp_id, $arrayVal, $context) {
        if (array_key_exists('ecomm_option_id', $arrayVal))
            unset($arrayVal['ecomm_option_id']);

        $vals = [[(int)$erp_id], $arrayVal];
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.product', 'write',
            [$vals], $userId, $client, $context['db'], $context['pwd'], $needContext = true);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'value' => False
            );
        } else {
            $product_id = $arrayVal['ecomm_id'];
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_template_merge` SET `is_synch`=0 where `opencart_product_id`='" . $product_id . "'");
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_variant_merge` SET `is_synch`=0 where `erp_product_id`='" . $erp_id . "'");
            return array(
                'value' => True
            );
        }
    }

    public function addto_product_merge($erp_product_id, $opencart_product_id, $cart_user = 'Default', $opencart_product_option_id=0) {
        if ($opencart_product_option_id!=0)
            $this->db->query("DELETE FROM ".DB_PREFIX."erp_product_variant_merge WHERE  `opencart_product_id`='".$opencart_product_id."'and `opencart_product_option_id`=0");
        $this->db->query("INSERT INTO ".DB_PREFIX."erp_product_variant_merge SET `erp_product_id`='".$erp_product_id."', `opencart_product_id`='".$opencart_product_id."',`opencart_product_option_id`='".$opencart_product_option_id."',`created_by`='".$cart_user."'");
    }

    public function addto_product_template_merge($erp_template_id, $opencart_product_id, $cart_user = 'Default'){
        $check_ExistEntry = $this->db->query("SELECT * FROM ".DB_PREFIX."erp_product_template_merge WHERE `opencart_product_id`='".$opencart_product_id."'")->row;
        if (empty($check_ExistEntry)) {
            $this->db->query("INSERT INTO ".DB_PREFIX."erp_product_template_merge SET `erp_template_id`='".$erp_template_id."', `opencart_product_id`='".$opencart_product_id."',`created_by`='".$cart_user."'");
        }
    }

    public function create_product_quantity($userId, $client, $erp_product_id, $product_quantity, $context){
        $arrayVal = array(
            'product_id' => (int)$erp_product_id,
            'new_quantity' => (int)$product_quantity
        );
        $needContext = ['stock_from' => 'opencart'];
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.snippet', 'update_quantity', [[$arrayVal]], $userId,
            $client,$context['db'], $context['pwd'], $needContext);
    }

    public function get_erp_category_id($userId, $client, $cart_product_id, $context){
        $data = $this->db->query("SELECT `category_id` FROM ". DB_PREFIX ."product_to_category where product_id='".$cart_product_id."'")->rows;
        $this->load->model('catalog/erp_product_category');
        $erp_category_ids = array();
        if (empty($data))
            return false;
        else {
            foreach ($data as $key => $value) {
                $erp_category_id = $this->model_catalog_erp_product_category->check_specific_category($value['category_id'], $userId, $client, $context['db'], $context['pwd'], $context['cart_user']);
                $erp_category_ids[] = (int)$erp_category_id;
            }
           return $erp_category_ids;
        }
    }

    public function validate_ean13($barcode) {
        if (!preg_match("/^[0-9]{13}$/", $barcode)) {
            return false;
        }
        $digits = $barcode;
        // 1. Add the values of the digits in the
        // even-numbered positions: 2, 4, 6, etc.
        $even_sum = $digits[1] + $digits[3] + $digits[5] +
                    $digits[7] + $digits[9] + $digits[11];
        // 2. Multiply this result by 3.
        $even_sum_three = $even_sum * 3;
        // 3. Add the values of the digits in the
        // odd-numbered positions: 1, 3, 5, etc.
        $odd_sum = $digits[0] + $digits[2] + $digits[4] +
                   $digits[6] + $digits[8] + $digits[10];
        // 4. Sum the results of steps 2 and 3.
        $total_sum = $even_sum_three + $odd_sum;
        // 5. The check character is the smallest number which,
        // when added to the result in step 4, produces a multiple of 10.
        $next_ten = (ceil($total_sum / 10)) * 10;
        $check_digit = $next_ten - $total_sum;
        // if the check digit and the last digit of the
        // barcode are OK return true;
        if ($check_digit == $digits[12]) {
            return $barcode;
        }
        return false;
    }

    //To search product in product merge table.
    private function search_product($product_id, $option_id){
        $product_check = $this->db->query("SELECT * from `" . DB_PREFIX . "erp_product_variant_merge` where `opencart_product_id`='".$product_id."' AND `opencart_product_option_id`='".$option_id."'")->row;

        if (isset($product_check['erp_product_id']) AND $product_check['erp_product_id'] > 0) {
            if ($product_check['is_synch'] == 1)
                return array(
                    -1,
                    $product_check['erp_product_id'],
                );
            else
                return array(
                    $product_check['erp_product_id'],
                );
        } else
            return array(
                0
            );
    }

    public function base64Image($path){
        $imageData = false;
        $image_url = DIR_IMAGE.$path;
        if (@file_get_contents($image_url)) {
            $content   = file_get_contents($image_url);
            $imageData = base64_encode($content);
        }
        return $imageData;
    }

    public function getOptionsDetails($product_option_value_id, $option_value_separator = ' - ', $option_separator = ', '){
        $q = "SELECT a.`option_id`, a.`option_value_id`,a.`quantity`,a.`price`,a.`price_prefix`, a.`weight_prefix`,a.`weight`,concat(agl.`name`, '".$option_value_separator."', al.`name`) as option_name
                FROM `". DB_PREFIX ."product_option_value` a

                LEFT JOIN `". DB_PREFIX ."option_description` agl ON (a.`option_id` = agl.`option_id`)

                LEFT JOIN `". DB_PREFIX ."option_value_description` al ON (a.`option_value_id` = al.`option_value_id`)

                WHERE a.product_option_value_id = '$product_option_value_id'";

        $data = $this->db->query($q)->rows;
        return $data;
    }

    public function get_template_data($product_id , $id_lang, $userId, $client, $context){
        $product_data  = $this->db->query("SELECT p.`weight`,p.`product_id`,p.`quantity`,p.`ean`,p.`image`,p.`price`,p.`sku`,pd.`name`,pd.`description` FROM ". DB_PREFIX ."product p
                LEFT JOIN `".DB_PREFIX."product_description` pd ON (p.`product_id` = pd.`product_id` AND pd.language_id =".(int)$id_lang.") WHERE p.product_id =".(int)$product_id."")->row;
        $temp_data = array(
            'name' => urlencode($product_data['name']),
            'description'=> urlencode($product_data['description']),
            'description_sale'=> urlencode($product_data['description']),
            'list_price'=>$product_data['price'],
            'weight'=>$product_data['weight'],
            // 'image_1920'=>$this->base64Image($product_data['image']),
            'image_url'=>HTTPS_CATALOG.'image/'.$product_data['image'],
            'default_code'=>$product_data['sku'],
            'barcode'=>$this->validate_ean13($product_data['ean']),
            'ecomm_id'=>$product_id,
            'type'=>'consu',
            'is_storable'=>true
        );
        $category_array = $this->get_erp_category_id($userId, $client, $product_id, $context);
        $temp_data['category_ids'] = $category_array;
        return $temp_data;
    }
    
    public function check_specific_product($product_id, $option_id, $userId, $client, $context){
        $id_lang = $context['lang_id'];
        $context['id_lang'] =  $id_lang;
        $cart_user = $context['cart_user'];
        $erp_product_id = false;
        $check_option = true;
        $sql_query = "SELECT  p.product_id, p.sku, p.ean, p.quantity FROM ". DB_PREFIX ."product p WHERE p.product_id = ".$product_id;
        $product_data = $this->db->query($sql_query)->row;
        if ($context['wkproducttype']=='template') {
            $check_option = false;
        }
        if (!$check_option) {
            $temp_data = $this->get_product_data($product_id, false, $id_lang, $userId, $client, $context);
            $product_quantity = $product_data['quantity'];
            $erp_product_id = $this->create_and_update_product($temp_data, $product_id, 0, $product_quantity, $userId, $client, $context);
        } else {
            $check_option_value_data = $this->db->query("SELECT `product_option_value_id`,`option_id`, `option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_id."' and product_option_value_id='".$option_id."'")->rows;
            if ($check_option_value_data) {
                foreach ($check_option_value_data as $option_data) {
                    $temp_data = $this->get_product_data($product_id, $option_data, $id_lang, $userId, $client, $context);
                    $temp_data['default_code'] = $product_data['sku'];
                    $product_quantity = $temp_data['product_quantity'];
                    unset($temp_data['product_quantity']);
                    $erp_product_id = $this->create_and_update_product($temp_data, $product_id, $option_data['product_option_value_id'], $product_quantity, $userId, $client, $context);
                }
            } else {
                $temp_data = $this->get_product_data($product_id, false, $id_lang, $userId, $client, $context);
                $product_quantity = $product_data['quantity'];
                $erp_product_id = $this->create_and_update_product($temp_data, $product_id, 0, $product_quantity, $userId, $client, $context);
            }
        }
        return array('erp_id' => $erp_product_id);
    }

    public function check_specific_template($product_id, $userId, $client, $context){
        $db         = $context['db'];
        $pwd        = $context['pwd'];
        $cart_user  = $context['cart_user'];
        $id_lang = $context['id_lang'];
        $erp = $this->check_template($userId, $client, $product_id);
        if ($erp[0] <= 0) {
			$temp_data = $this->get_template_data($product_id, $id_lang, $userId, $client, $context);
            if ($erp[0] == 0) {
                $create = $this->create_template($userId, $client, $temp_data, $context);
                $erp_template_id = $create['erp_id'];
                $this->create_product_attribute_line($erp_template_id, $product_id, $userId, $client, $context);
            } else {
                $this->update_template($userId, $client, $erp[1], $temp_data, $context);
                $erp_template_id = $erp[1];
                $this->create_product_attribute_line($erp_template_id, $product_id, $userId, $client, $context);
            }
        } else {
           $erp_template_id = $erp[0];
        }
        return $erp_template_id;
    }

    public function create_product_attribute_line($erp_template_id, $product_id, $userId, $client, $context)
    {
        $this->load->model('catalog/erp_product_options');
        $this->load->model('catalog/connection');
        $ps_attributes =array();
        $result = $this->db->query("SELECT `product_option_value_id`,`option_id`,`option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_id."'")->rows;
        foreach ($result as $attribute_data) {
            if(array_key_exists($attribute_data['option_id'], $ps_attributes))
                array_push($ps_attributes[$attribute_data['option_id']] , $attribute_data['option_value_id']);
            else
                $ps_attributes[$attribute_data['option_id']] = array($attribute_data['option_value_id']);
        }
        foreach ($ps_attributes as $attribute_id => $attribute_values) {
            $data = array(
                'product_tmpl_id' => (int)$erp_template_id
            );
            $erp_attribute_id = false;
            foreach($attribute_values as $attribute_value) {
                if(!$erp_attribute_id)
                    $erp_attribute_id = $this->model_catalog_erp_product_options->check_specific_options($userId, 
                        $client, $attribute_id, $context);

                $erp_attribute_value_id = $this->model_catalog_erp_product_options->check_specific_options_value($userId, 
                    $client, $attribute_value, $context);
            
                if(!$erp_attribute_id and !$erp_attribute_value_id)
                    continue;
                $data['values'][] = array( 'value_id' =>(int) $erp_attribute_value_id);
            }
            $data['attribute_id'] =(int)$erp_attribute_id;
            $resp = $this->model_catalog_connection->callOdooRpc('connector.template.mapping', 'create_n_update_attribute_line', [[$data]], $userId, $client, $context['db'], $context['pwd'], $needContext = true);
       }
    }

    public function check_template($userId, $client, $product_id){
        $check_erp_id = $this->db->query("SELECT `is_synch`,`erp_template_id` from `" . DB_PREFIX . "erp_product_template_merge` where `opencart_product_id`=" . $product_id . "")->row;
        if (isset($check_erp_id['erp_template_id']) && $check_erp_id['erp_template_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1){
                return array(
                    -1,
                    $check_erp_id['erp_template_id']
                );
            } else {
                return array(
                    $check_erp_id['erp_template_id']
                );
            }
        } else {
            return array(
                0
            );
        }
    }

    public function create_template($userId, $client, $template_data, $context){
        if (!$template_data['type']) {
            $$template_data['type'] = 'consu';
            $$template_data['is_storable'] = true;
        }
        $needContext= ['configurable' => 'true'];
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.template', 'create', [[$template_data]], $userId, $client, $context['db'], $context['pwd'], $needContext);
        if ($resp[0]==0) {
            $error_message = $resp[1];
            return array(
                'error_message' => $error_message,
                'erp_id' => -1,
                'product_id' => -1
            );
        } else {
            $erp_template_id = $resp[1];
            $this->addto_product_template_merge($erp_template_id, $template_data['ecomm_id'], $context['cart_user']);
            return array(
                'erp_id' => $erp_template_id
            );
        }
    }

    public function update_template($userId, $client, $erp_id, $template_data, $context){
        $vals =[[(int)$erp_id], $template_data];
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('product.template', 'write', [$vals], $userId, $client, $context['db'], $context['pwd'], $needContext=true);
        if ($resp[0]==0) {
            $error_message = $resp[1];
            return array(
                'error_message' => $error_message,
                'value' => False
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_template_merge` SET `is_synch`=0 where `erp_template_id`='" . $erp_id . "'");
            return array(
                'value' => True
            );
        }
    }
}
?>
