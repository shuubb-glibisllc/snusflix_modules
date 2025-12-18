<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpState extends Model {

    //iso code of state
    public function get_state_dtls($id_state){
        $state_dtls = $this->db->query("SELECT * from `" . DB_PREFIX . "zone` where `zone_id`='$id_state' ")->row;
        return $state_dtls;
    }
}
?>
