<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpCountry extends Model {

    // iso code of country.
    public function get_iso($id_country){
        $country_iso_code = $this->db->query("SELECT `iso_code_2` from `" . DB_PREFIX . "country` where country_id='$id_country'")->row;
        return $country_iso_code['iso_code_2'];
    }

    // name of country.
    public function get_country_name($id_country){
        $country_name = $this->db->query("SELECT `name` from `" . DB_PREFIX . "country` where `country_id`='$id_country'")->row;
        return $country_name['name'];
    }
}
?>
