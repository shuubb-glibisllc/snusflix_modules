<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpAddress extends Model {

    public function getCustomerAddress($customerId) {

    	$results = $this->db->query("SELECT * FROM ".DB_PREFIX."address WHERE customer_id = '".(int)$customerId."'")->rows;

    	return $results;
    }

    public function getErpAddressArray($erp_customer_id, $userId, $client, $db, $pwd) {
        $Address = array();
        $condition=[[['parent_id','=',(int)$erp_customer_id]]];
        $this->load->model('catalog/connection');
        $response1 = $this->model_catalog_connection->callOdooRpc('res.partner',
            'search', [$condition], $userId, $client, $db, $pwd, $needContext=false);
        if ($response1[0]==0) {
            array_push($Address, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
        } else {
            $condition = array('fields'=>['id','name', 'street' , 'city']);
            $vals = array([$response1[1]], $condition);
            $resp1 = $this->model_catalog_connection->callOdooRpc('res.partner',
                'read', $vals, $userId, $client, $db, $pwd, $needContext=false);
            if ($resp1[0]==0) {
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Address, array('label' => $msg, 'id' => ''));
            } else {
                $value_array = $resp1[1];
                $count       = count($value_array);
                if ($count==0) {
                    $arr = false;
                }
                for ($x = 0; $x < $count; $x++) {
                    $id = $value_array[$x]['id'];
                    $name = $value_array[$x]['name'];
                    $street = $value_array[$x]['street'];
                    $city = $value_array[$x]['city'];
                    array_push($Address,
                     array(
                             'id' => $id,
                             'name'=>$name.', '.$street.', '.$city
                        )
                     );
                }
            }
        }
        return $Address;
    }
}
?>
