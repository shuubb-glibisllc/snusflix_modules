<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpproductcategory  extends Model{

public function check_all_categories($context){
    // Add timeout protection
    set_time_limit(600); // Increase timeout
    ini_set('memory_limit', '512M');
    $is_error       = 0;
    $error_message  = '';
    $ids            = '';
    
    $offset = 0;
    $batch_size = 50;
    $total_synced = 0;
    
    do {
        $data = $this->db->query("SELECT c.category_id,cd.name,c.parent_id FROM ".DB_PREFIX."category c LEFT JOIN ".DB_PREFIX."category_description cd ON(c.category_id = cd.category_id) WHERE c.category_id NOT IN (SELECT opencart_category_id FROM " .DB_PREFIX."erp_category_merge) AND c.status = 1 ORDER BY c.parent_id, c.category_id LIMIT ".$batch_size." OFFSET ".$offset)->rows;
        
        if (count($data) == 0) {
            break;
        }
        
        foreach ($data as $cat_id) {
            $this->sync_categories($cat_id['category_id'], $context);
            $total_synced++;
        }
        
        $offset += $batch_size;
        
    } while (count($data) == $batch_size);
    
    return array(
        'is_error' => $is_error,
        'error_message' => $error_message,
        'value' => $total_synced > 0 ? 1 : 0,
        'ids' => $ids
    );
}
    //To synch categories with its structure.
    public function sync_categories($opencart_id, $context){

        $check_obj = $this->check_category($opencart_id);

        if ($check_obj[0] == 0) {
            $db_object          = $this->get_cat_info($opencart_id);
            $name               = html_entity_decode($db_object['name']);
            $opencart_parent_id = $db_object['id_parent'];
            if ($opencart_parent_id > 0)
                $erp_parent_id = $this->sync_categories($opencart_parent_id, $context);
            else {
                $erp_id = $this->create_category($opencart_id, false, $name, $context);
                return $erp_id['erp_id'];
            }

            $erp_id = $this->create_category($opencart_id, $erp_parent_id, $name, $context);
            return $erp_id['erp_id'];
        } elseif ($check_obj[0] == -1) {
            $db_object           = $this->get_cat_info($opencart_id);
            $name                = html_entity_decode($db_object['name']);
            $opencart_parent_id = $db_object['id_parent'];

            if ($opencart_parent_id > 0)
                $erp_parent_id = $this->sync_categories($opencart_parent_id, $context);
            else
                $erp_parent_id = false;

            $this->update_category($opencart_id, $erp_parent_id, $name, $check_obj[1], $context);
            return $check_obj[1];
        } else
            return $check_obj[0];
    }

    //To check categories in Merge Table.
    public function check_category($opencart_id){
        $categ_obj = $this->db->query("SELECT `erp_category_id`,`is_synch` from `" . DB_PREFIX . "erp_category_merge` where `opencart_category_id` = '" . $opencart_id . "'")->row;
        if (isset($categ_obj['erp_category_id']) && $categ_obj['erp_category_id'] > 0) {
            if ($categ_obj['is_synch'] == 1)
                return array(
                    -1,
                    $categ_obj['erp_category_id']
                );
            else
                return array(
                    $categ_obj['erp_category_id']
                );
        } else
            return array(
                0
            );
    }

    // the parent id of category
    public function get_cat_info($opencart_id){
        $db_object  = $this->db->query("SELECT `parent_id`,`name` from `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON(c.category_id = cd.category_id) where c.category_id='$opencart_id'")->row;

        if($db_object)
            return array(
                'id_parent' => $db_object['parent_id'],
                'name' => $db_object['name']
            );
        else
            return array(
                'id_parent' => '0',
                'name' => 'No Category'
            );
    }

     //To create category at erp end.
     private function create_category($opencart_id, $id_parent = 0, $name, $context){
        $userId         = $context['userId'];
        $client         = $context['client'];
        $db             = $context['db'];
        $pwd            = $context['pwd'];
        $cart_user      = $context['cart_user'];
        $context = ['created_by'=> 'opencart'];
        $key = array(
            'name' =>$name,
            'method' => 'create',
            'ecomm_id'=>$opencart_id
        );
        if ($id_parent)
            $key['parent_id'] = (int)$id_parent;
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.category.mapping', 'create_category', [[$key]], $userId, $client, $db, $pwd, $context);
        if ($resp[0]==0) {
            $error_message = $resp[1];
            return array(
                'error_message' => $error_message,
                'value' => false,
                'erp_id' => -1,
            );
        } else {
            $erp_id = $resp[1];
            $this->addto_category_merge($erp_id, $opencart_id, $cart_user);
            return array(
                'erp_id' => $erp_id
            );
        }
    }

    public function addto_category_merge($erp_id, $opencart_id, $cart_user){
        $this->db->query("INSERT INTO ".DB_PREFIX."erp_category_merge SET erp_category_id='$erp_id',opencart_category_id='$opencart_id',created_by='$cart_user'");
    }

    public function check_specific_category($cartid, $userId, $client, $db, $pwd, $cart_user='Front End'){
        $context = array(
            'db' => $db,
            'pwd' => $pwd,
            'client' => $client,
            'userId' => $userId,
            'cart_user' => $cart_user
        );

        $erp_id = $this->sync_categories($cartid, $context);
        return $erp_id;
    }

    public function update_category($cart_id, $id_parent, $name, $erp_id, $context){
        $userId         = $context['userId'];
        $client         = $context['client'];
        $db             = $context['db'];
        $pwd            = $context['pwd'];
        $cart_user      = $context['cart_user'];
        $key   = array(
            'name' => $name,
            'method' => 'write',
            'category_id' => (int)$erp_id
        );
        if ($id_parent)
            $key['parent_id'] = (int)$id_parent;
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.category.mapping', 'create_category', [[$key]], $userId, $client, $db, $pwd, $needContext = true);
    }

    public function chkErpOpencartCategories($erp_id,$cart_id){
        $chk  = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_category_merge` WHERE opencart_category_id='$cart_id' or erp_category_id='$erp_id'")->row;
        if($chk)
            return false;
        return true;
    }

    public function getErpCategoryArray($userId, $client, $db, $pwd, $cart_user) {
        $Category = array();
        $condition=[[]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('product.category', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Category, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition = array('fields'=>['id','name']);
            $vals = array([$response1[1]], $condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('product.category', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Category, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
                    array_push($Category,
                        array(
                            'id' => $id,
                            'name'=>$name
                        )
                    );
                }
            }
        }
        return $Category;
    }
}
?>
