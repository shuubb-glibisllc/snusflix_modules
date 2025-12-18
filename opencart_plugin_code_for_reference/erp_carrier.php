<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpCarrier extends Model {

    public function check_all_carriers($userId, $client, $db, $pwd, $cart_user){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $check         = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` NOT IN (SELECT `opencart_carrier_cod` FROM `" . DB_PREFIX . "erp_carrier_merge` WHERE `is_synch` = 0) AND type = 'shipping' ")->rows;

        if (count($check) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($check as $data) {
            $erp_carrier_id = $this->check_carrier($data['code']);

            $extension = basename($data['code'], '.php');
            $this->language->load('extension/shipping/' . $extension);
            $data['name']  = $this->language->get('heading_title');

            if ($erp_carrier_id[0] == 0) {
                $create = $this->create_carrier($data['name'], $userId, $client, $cart_user, $db, $pwd);

                if ($create['erp_id'] > 0)
                    $this->addto_carrier_merge($data['code'], $create['erp_id'], $data['name'], $cart_user);
                else {
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $data['code'] . ',';
                }
            }
            if ($erp_carrier_id[0] < 0) {
                $update = $this->update_carriers($erp_carrier_id[1], $data['code'], $data['name'], $userId, $client, $cart_user, $db, $pwd);
                if ($update['value'] != True) {
                    $is_error = 1;
                    $error_message .= $update['error_message'] . ',';
                    $ids .= $data['code'] . ',';
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

    public function check_specific_carrier($carrier_id, $userId, $client, $db, $pwd){

        $carrier_id = explode('.', $carrier_id);
        $erp = $this->check_carrier($carrier_id[0]);
        $check = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` = '".$carrier_id[0]."' AND type='shipping'")->row;

        $extension = basename($check['code'], '.php');
        $this->language->load('extension/shipping/' . $extension);
        $check['name']  = $this->language->get('heading_title');

        if ($erp[0] == 0) {
            $create = $this->create_carrier($check['name'], $userId, $client, 'Front End', $db, $pwd);
            $this->addto_carrier_merge($carrier_id[0], $create['erp_id'], $check['name']);
            $erp_id = $create['erp_id'];
        }
        if ($erp[0] < 0) {
            $update = $this->update_carriers($erp[1], $check['code'], $check['name'], $userId, $client, 'Front End', $db, $pwd);
            $erp_id=$erp[1];
        }
        if ($erp[0] > 0)
            $erp_id=$erp[0];
        return array('name'=>$check['code'],'erp_id'=>$erp_id);
    }

    public function check_carrier($id_carrier){
        $check_erp_id = $this->db->query("SELECT `is_synch`,`erp_carrier_id` from `" . DB_PREFIX . "erp_carrier_merge` where `opencart_carrier_cod`= '$id_carrier'")->row;
        if (isset($check_erp_id['erp_carrier_id']) AND $check_erp_id['erp_carrier_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1)
                return array(
                    -1,
                    $check_erp_id['erp_carrier_id']
                );
            else
                return array(
                    $check_erp_id['erp_carrier_id']
                );
        } else
            return array(
                0
            );
    }
   
    public function create_carrier($name, $userId, $client, $cart_user, $db, $pwd){
        $key = array(
                'name' => str_replace('+', ' ', urlencode($name)),
            );
        $this->load->model('catalog/connection');
        $resp = $this->model_catalog_connection->callOdooRpc('delivery.carrier', 'create', [[$key]], $userId, $client, $db, $pwd, $needcontext=true);
        
        if($resp[0]==0) {
            return array(
                'error_message' => $resp[1],
                'erp_id' => -1
            );
        } else {
            return array(
                'erp_id' => $resp[1],
            );
        }
    }

    public function addto_carrier_merge($carrier_id, $erp_carrier_id, $name, $cart_user = 'Front End'){
        $data = array(
            'erp_carrier_id' => $erp_carrier_id,
            'opencart_carrier_cod' => $carrier_id,
            'created_by' => $cart_user,
            'name' => $name
        );

        $this->db->query("INSERT INTO " . DB_PREFIX . "erp_carrier_merge SET erp_carrier_id = '$erp_carrier_id' , opencart_carrier_cod = '$carrier_id' , name = '$name', created_by = '$cart_user', created_on = NOW() ");
    }

    public function update_carriers($erp_carrier_id, $id_carrier, $name, $userId, $client, $cart_user, $db, $pwd){

        $this->load->model('catalog/connection');
     
        $key =[[(int)$erp_carrier_id], ['name'=>$name]];

        $resp = $this->model_catalog_connection->callOdooRpc('delivery.carrier', 'write', [$key], $userId, $client, $db, $pwd, $needcontext=true);
        if ($resp[0]==0) {
            return array(
                'value' => false,
                'error_message' => $resp[1],
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_carrier_merge` SET `is_synch`=0 where `opencart_carrier_cod`= '$id_carrier'");
            return array(
                'value' => True
            );
        }
    }

    public function chk_carrier_merge($cart_id, $erp_id){
        $check = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_carrier_merge` WHERE `opencart_carrier_cod` = '".$cart_id."' OR erp_carrier_id = '$erp_id'")->row;
        if($check)
            return False;
        else
            return true;
    }

    public function getErpCarrierArray($userId, $client, $db, $pwd) {
        $Carrier = array();
        $condition=[[]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('delivery.carrier', 'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Carrier, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition=array('fields'=>['id','name']);
            $vals = array([$response1[1]],$condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('delivery.carrier', 'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Carrier, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
                    array_push($Carrier,
                        array(
                            'id' => $id,
                            'name'=>$name
                        )
                    );
                }
            }
        }
        return $Carrier;
    }
}
?>
