<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpPayment extends Model
{

	public function check_all_payment_methods($userId, $client, $db, $pwd, $cart_user)
    {
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $count         = 0;

        $data = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` NOT IN (SELECT `opencart_payment_cod` FROM `" . DB_PREFIX . "erp_payment_merge` WHERE `is_synch` = 0) AND type = 'payment' ")->rows;

        if (count($data) == 0) {
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }
        foreach ($data as $payment) {

        	$extension = basename($payment['code'], '.php');
            $this->language->load('extension/payment/' . $extension);
            $payment['name']  = $this->language->get('heading_title');

            $opencart_id = $payment['code'];
            $check         = $this->db->query("SELECT * from `" . DB_PREFIX . "erp_payment_merge` where `opencart_payment_cod`='$opencart_id'")->row;

            $module = $payment['name'];
            if (isset($check['erp_payment_id']) AND $check['erp_payment_id'] <= 0) {
                if ($module == false) {
                    continue;
                }
            } else {
                $create = $this->merge_payment_method($module, $userId, $client, $db, $pwd);
                if ($create['erp_id'] > 0) {
                    $this->addto_payment_merge($create['erp_id'], $payment['name'], $opencart_id, $cart_user);
                    $count = 1;
                } else {
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $opencart_id . ',';
                }
            }
        }
        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => $count,
            'ids' => $ids
        );
    }

    public function merge_payment_method($name, $userId, $client, $db, $pwd){
        $key = array(
            'name' => $name,
            'type'=>'cash',
        );
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('connector.snippet', 'create_payment_method', [[$key]], $userId, $client, $db, $pwd, $needContext = true);
        if(!$resp[1]['status']) {
            return array(
                'error_message' => $resp[1]['status_message'],
                'erp_id' => -1
            );
        } else {
            return array(
                'erp_id' => $resp[1]['odoo_id'],
            );
        }
    }

    public function addto_payment_merge($erp_id, $name, $op_id, $cart_user = 'Front End'){
        $data = array(
            'erp_payment_id' => $erp_id,
            'opencart_payment_id' => $op_id,
            'created_by' => $cart_user,
            'name' => $name
        );
        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_payment_merge` SET erp_payment_id = '$erp_id' , opencart_payment_cod = '$op_id' , name = '$name', created_by = '$cart_user', created_on = NOW() ");
    }

    public function check_specific_payment_method($name, $code, $userId, $client, $db, $pwd){
        $check =  $this->db->query("SELECT * from `" . DB_PREFIX . "erp_payment_merge` where `opencart_payment_cod`='$code'")->row;
        if (empty($check)) {
            $extension = basename($code, '.php');
            $this->language->load('extension/payment/' . $extension);
            // $code  = $this->language->get('heading_title');
            $create = $this->merge_payment_method($name, $userId, $client, $db, $pwd);
            $this->addto_payment_merge($create['erp_id'], $name, $code);
            return $create['erp_id'];
        } else {
            return $check['erp_payment_id'];
        }
    }

    public function chk_payment_merge($payment_code){
        $chk = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_payment_merge` WHERE `opencart_payment_cod`='$payment_code'")->row;
        return $chk;
    }

    public function getErpPaymentArray($userId, $client, $db, $pwd, $cart_user) {

        $Payment = array();
        $key = ['bank', 'cash'];
        $condition=[[['type' , 'in' , $key]]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('account.journal', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Payment, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition=array('fields'=>['id','name']);
            $vals = array([$response1[1]],$condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('account.journal', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Payment, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
                    array_push($Payment,
                        array(
                            'id' => $id,
                            'name'=>$name
                        )
                    );
                }
            }
        }
        return $Payment;
    }
}
