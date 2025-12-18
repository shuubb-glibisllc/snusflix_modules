<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpTax extends Model {

    public function check_all_taxes($userId, $client, $db, $pwd, $op_user){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $data          = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_rate  WHERE `tax_rate_id` NOT IN (SELECT `opencart_tax_id` FROM `" . DB_PREFIX . "erp_tax_merge` WHERE `is_synch` = 0 )")->rows;
        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }
        foreach ($data as $tax) {
            $check_tax = $this->check_tax($tax['tax_rate_id']);
            if ($check_tax[0] <= 0) {
                if ($check_tax[0] == 0) {
                    $create = $this->create_tax($tax, $client, $userId, $db, $pwd);
                    if ($create['erp_id'] > 0)
                        $this->addto_tax_merge($create['erp_id'], $tax, $op_user);
                    else {
                        $is_error = 1;
                        $error_message .= $create['error_message'] . ',';
                        $ids .= $tax['tax_rate_id'] . ',';
                    }
                } else {
                    $update = $this->update_taxes($check_tax[1], $tax, $client, $userId, $db, $pwd);
                    if ($update['value'] != True) {
                        $is_error = 1;
                        $error_message .= $update['error_message'] . ',';
                        $ids .= $tax['tax_rate_id'] . ',';
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

    public function check_tax($tax_id){
        $check_tax = $this->db->query("SELECT `is_synch`,`erp_tax_id` from `" . DB_PREFIX . "erp_tax_merge` where `opencart_tax_id`='" . $tax_id . "'")->row;
        if ($check_tax) {
            if ($check_tax['is_synch'] == 1)
                return array(
                    -1,
                    $check_tax['erp_tax_id']
                );
            else
                return array(
                    $check_tax['erp_tax_id']
                );
        } else
            return array(
                0
            );
    }

    public function create_tax($tax, $client, $userId, $db, $pwd){
        if ($tax['type'] == 'P') {
            $tax_rate = $tax['rate'];
            $tax_type = 'percent';
        } elseif($tax['type'] == 'F') {
            $tax_rate = $tax['rate'];
            $tax_type = 'fixed';
        }
        $tax_name = $tax['name'].'_'.$tax['tax_rate_id'];
        $key = array(
            'name' => $tax_name,
            'amount' => $tax_rate,
            'amount_type' => $tax_type,
        );
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('account.tax', 'create', [[$key]], $userId, $client, $db, $pwd, $needContext = true);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'erp_id' => -1,
            );
        } else {
            $erp_id = $resp[1];
            return array(
                'erp_id' => $erp_id
            );
        }
    }

    public function addto_tax_merge($erp_tax_id, $tax_data, $op_user = 'Front End'){
        // $db_tax  = $this->db->query("SELECT `rate`  from `" . DB_PREFIX . "tax_rate` where `tax_rate_id`='" . $tax_id . "'")->row;
        $oc_tax_id = $tax_data['tax_rate_id'];
        $rate = $tax_data['rate'];
        $tax_name = $tax_data['name'];

        $chktax_id  = $this->db->query("SELECT `id`  from `" . DB_PREFIX . "erp_tax_merge` where `opencart_tax_id`=" . $tax_data['tax_rate_id'] . "")->row;

        if (!$chktax_id) {
            $this->db->query("INSERT INTO `".DB_PREFIX. "erp_tax_merge` SET erp_tax_id = '$erp_tax_id' ,opencart_tax_id = '$oc_tax_id', rate = '$rate' ,oc_tax_name = '$tax_name' , created_by = '$op_user', created_on = NOW() ");
            return true;
        } else {
            return false;
        }
    }

    public function update_taxes($erp_tax_id, $tax, $client, $userId, $db, $pwd){
        $op_tax_id = $tax['tax_rate_id'];
        if ($tax['type'] == 'P') {
            $tax_rate = $tax['rate'];
            $tax_type = 'percent';
        } elseif ($tax['type'] == 'F') {
            $tax_rate = $tax['rate'];
            $tax_type = 'fixed';
        }
        $tax_name = $tax['name'].'_'.$tax['tax_rate_id'];
        $key = array(
            'name' => $tax_name,
            'amount' => $tax_rate,
            'amount_type' => $tax_type,
        );
        $vals =[[(int)$erp_tax_id], $key];
        $this->load->model('catalog/connection');
        
        $resp = $this->model_catalog_connection->callOdooRpc('account.tax', 'write', [$vals], $userId, $client, $db, $pwd, $needContext = true);
        if ($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'value' => false,
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_tax_merge` SET `is_synch`=0 where `opencart_tax_id`='" . $op_tax_id . "'");
            return array(
                'value' => True
            );
        }
    }

    public function check_specific_tax($id_tax, $client, $userId, $db, $pwd){
        $check_tax = $this->check_tax($id_tax);
        if ($check_tax[0] > 0)
            return $check_tax[0];
        else {
            $db_tax  = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_rate  WHERE `tax_rate_id` ='" . $id_tax . "'")->row;
            if ($check_tax[0] == 0) {
                $create = $this->create_tax($db_tax, $client, $userId, $db, $pwd);
                $this->addto_tax_merge($create['erp_id'], $db_tax, 'Front End');
                return $create['erp_id'];
            } else {
                $update = $this->update_taxes($check_tax[1], $db_tax, $client, $userId, $db, $pwd);
                return $check_tax[1];
            }
        }
    }

    public function getErpTaxArray($userId, $client, $db, $pwd) {
        $Tax = array();
        $condition=[[]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('account.tax', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Tax, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition=array('fields'=>['id','name','amount']);
            $vals = array([$response1[1]],$condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('account.tax', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Tax, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'].' - '.$value_array[$x]['amount'];
                    array_push($Tax,
                        array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }
        return $Tax;
    }
}
?>
