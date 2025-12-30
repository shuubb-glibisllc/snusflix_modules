<?php
// Deployment script to backup old files and deploy new ones
header('Content-Type: text/plain');

echo "=== OPENCART SYNC FIX DEPLOYMENT ===\n\n";

$timestamp = date('Y-m-d_H-i-s');

// Step 1: Backup existing files
echo "1. Backing up existing files...\n";

$api_source = 'catalog/controller/api/oob.php';
$api_backup = 'catalog/controller/api/oob_old.php';
$model_source = 'admin/model/catalog/wk_webservices_tab.php';
$model_backup = 'admin/model/catalog/wk_webservices_tab_old.php';

if (file_exists($api_source)) {
    if (copy($api_source, $api_backup)) {
        echo "✓ Backed up API controller to oob_old.php\n";
    } else {
        echo "❌ Failed to backup API controller\n";
    }
} else {
    echo "⚠ API controller not found at expected location\n";
}

if (file_exists($model_source)) {
    if (copy($model_source, $model_backup)) {
        echo "✓ Backed up model to wk_webservices_tab_old.php\n";
    } else {
        echo "❌ Failed to backup model\n";
    }
} else {
    echo "⚠ Model not found at expected location\n";
}

// Step 2: Deploy new files
echo "\n2. Creating fixed API controller...\n";

$fixed_api_content = '<?php
################################################################################################
# Fixed Webservices API Controller for OpenCart 3.x.x.x - OOB Bridge
################################################################################################

$ADMIN_PATH = DIR_SYSTEM . \'../admin/\';
require_once ($ADMIN_PATH . \'model/catalog/wk_webservices_tab.php\');

class ControllerApiOob extends Controller {
    
    private function logDebug($message) {
        $log_message = date(\'Y-m-d H:i:s\') . " - FIXED OOB DEBUG: " . $message . "\\n";
        file_put_contents(DIR_SYSTEM . \'../oob_fixed_debug.log\', $log_message, FILE_APPEND);
        error_log("FIXED OOB DEBUG: " . $message);
    }

    public function login(){
        $message = \'\';
        $status = False;
        $params = $this->request->post;

        //Accepting data in json format / raw data
        $raw_data = json_decode(file_get_contents("php://input"), true);
        if ($raw_data) {
            foreach ($raw_data as $key => $value) {
                $params[$key] = $value;
            }
        }

        if (isset($params) && isset($params[\'api_key\']) && trim($params[\'api_key\'])) {
            $obj = new ModelCatalogWkWebservicesTab($this->registry);
            $total = $obj->userValidation($params);

            if ($total) {
                $message = $total;
                $status = True;
            } else {
                $message = \'Details - api_user and api_key are not correct !!!\';
            }
        } else {
            $message =\'Method must have a parameter(array type). Array must contains api_user and api_key!!!\';
        }

        $this->response->addHeader(\'Content-Type: application/json\');
        $this->response->setOutput(json_encode(array($message, $status)));
    }

    public function Validate_session_key($session){
        $connection = false;
        $message =  \'\';
        if (!is_string($session)) {
            $message = \'First parameter is reserved for session key, and it should be string type!!!\';
        } elseif (isset($session) && trim($session)) {
            $sql ="SELECT id FROM " . DB_PREFIX . "api_keys  WHERE Auth_key=\'".$session."\'";
            $result=$this->db->query($sql);

            if (isset($result->row[\'id\'])) {
                $connection = true;
            } else {
                $message = \'Session Key is not validated!!!\';
            }
        } else {
            $message = \'Method must have a parameter(string type)!!!\';
        }

        return array($connection, $message);
    }

    public function product() {
        $connection = false;
        $message = \'\';
        $last_id = 0;
        $status = False;
        $session = \'\';
        $params = $this->request->post;

        // Enhanced debug logging
        $this->logDebug("Product API called with method: " . $_SERVER[\'REQUEST_METHOD\']);
        
        //Accepting data in json format / raw data
        $raw_data = json_decode(file_get_contents("php://input"), true);
        if ($raw_data) {
            foreach ($raw_data as $key => $value) {
                $params[$key] = $value;
            }
        }

        $this->logDebug("Raw input data: " . json_encode($raw_data));
        $this->logDebug("Final params: " . json_encode($params));

        if (isset($params[\'session\']) && $params[\'session\']) {
            $session = $params[\'session\'];
            unset($params[\'session\']);
        }

        if (isset($this->request->get[\'session\']) && $this->request->get[\'session\']) {
            $session = $this->request->get[\'session\'];
        }

        $connection = $this->Validate_session_key($session);

        if ($connection[0]) {
            //Add product
            if (!empty($params)) {
                $this->logDebug("Add product mode initiated");
                $message = \'\';

                $obj = new ModelCatalogWkWebservicesTab($this->registry);

                // Enhanced validation
                if (!isset($params[\'erp_product_id\'])) {
                    $message = \'Parameter array must have erp product id for merge data !!!\';
                    $this->logDebug("Validation failed: missing erp_product_id");
                } elseif (!isset($params[\'name\'])) {
                    $message = \'Parameter array must have product name !!!\';
                    $this->logDebug("Validation failed: missing name");
                }

                $language_id = $this->config->get(\'config_language_id\');
                if (!$language_id && $message == \'\') {
                    $message = \'OpenCart Config Language Not Found!!!\';
                    $this->logDebug("Validation failed: missing language_id");
                }

                $this->logDebug("Validation result: " . ($message == \'\' ? \'PASSED\' : \'FAILED - \' . $message));

                // Enhanced default data structure
                $data = array (
                    \'model\' => isset($params[\'model\']) ? $params[\'model\'] : \'DEFAULT-\' . time(),
                    \'sku\' => isset($params[\'sku\']) ? $params[\'sku\'] : \'\',
                    \'location\' => \'\',
                    \'quantity\' => isset($params[\'quantity\']) ? (int)$params[\'quantity\'] : 0,
                    \'minimum\' => 1,
                    \'subtract\' => 1,
                    \'stock_status_id\' => isset($params[\'quantity\']) && $params[\'quantity\'] > 0 ? 7 : 5,
                    \'date_available\' => date(\'Y-m-d\'),
                    \'manufacturer_id\' => 0,
                    \'shipping\' => 1,
                    \'price\' => isset($params[\'price\']) ? (float)$params[\'price\'] : 0.00,
                    \'points\' => 0,
                    \'weight\' => isset($params[\'weight\']) ? (float)$params[\'weight\'] : 0.00,
                    \'weight_class_id\' => 1,
                    \'length\' => 0,
                    \'width\' => 0,
                    \'height\' => 0,
                    \'length_class_id\' => 1,
                    \'status\' => 1,
                    \'tax_class_id\' => 0,
                    \'sort_order\' => 0,
                    \'keyword\' => \'\',
                    \'erp_product_id\' => isset($params[\'erp_product_id\']) ? (int)$params[\'erp_product_id\'] : 0,
                    \'erp_template_id\' => isset($params[\'erp_template_id\']) ? (int)$params[\'erp_template_id\'] : 0,
                    \'created_by\' => \'From Odoo Fixed\',
                    \'product_category\' => array(),
                    \'product_description\' => array(
                        $language_id => array(
                            \'name\' => isset($params[\'name\']) ? $params[\'name\'] : \'Default Product\',
                            \'meta_keyword\' => \'\',
                            \'meta_description\' => \'\',
                            \'description\' => isset($params[\'description\']) ? $params[\'description\'] : \'\',
                            \'tag\' => \'\'
                        )
                    ),
                );

                // Handle categories properly
                if (isset($params[\'product_category\']) && is_array($params[\'product_category\'])) {
                    $valid_categories = array();
                    foreach ($params[\'product_category\'] as $cat_id) {
                        $cat_check = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category WHERE category_id = " . (int)$cat_id . " AND status = 1");
                        if ($cat_check->num_rows > 0) {
                            $valid_categories[] = (int)$cat_id;
                        }
                    }
                    
                    if (empty($valid_categories)) {
                        $default_cat = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category WHERE status = 1 ORDER BY category_id LIMIT 1");
                        if ($default_cat->num_rows > 0) {
                            $valid_categories[] = (int)$default_cat->row[\'category_id\'];
                        }
                    }
                    
                    $data[\'product_category\'] = $valid_categories;
                }

                foreach ($params as $key => $value) {
                    if ($key == \'name\' || $key == \'meta_keyword\' || $key == \'meta_description\' || $key == \'description\' || $key == \'tag\') {
                        $data[\'product_description\'][$language_id][$key] = $params[$key];
                    } elseif (!isset($data[$key])) {
                        $data[$key] = $params[$key];
                    }
                }

                $data[\'product_store\'] = array(0);

                if ($message == \'\') {
                    try {
                        $this->logDebug("Starting product creation");
                        $result = $obj->addProduct($data);
                        
                        if ($result && isset($result[\'product_id\']) && $result[\'product_id\'] > 0) {
                            $last_id = $result;
                            $status = True;
                            $message = \'Product successfully added. Product Id: \' . $result[\'product_id\'];
                            $this->logDebug("Product created successfully: " . $result[\'product_id\']);
                        } else {
                            $status = False;
                            $message = \'Product creation failed\';
                            $this->logDebug("Product creation failed");
                        }
                        
                    } catch (Exception $e) {
                        $this->logDebug("Exception: " . $e->getMessage());
                        $status = False;
                        $message = \'Product creation failed: \' . $e->getMessage();
                    }
                }

                $this->response->addHeader(\'Content-Type: application/json\');
                $response_data = array($message, $last_id, $status);
                $this->logDebug("Response: " . json_encode($response_data));
                $this->response->setOutput(json_encode($response_data));
            } else {
                $message = \'No Params Find In The Url\';
                $this->response->addHeader(\'Content-Type: application/json\');
                $this->response->setOutput(json_encode(array($message, 0)));
            }
        } else {
            $this->response->addHeader(\'Content-Type: application/json\');
            $this->response->setOutput(json_encode(array($connection[1], $last_id, $status)));
        }
    }

    // Simplified other methods
    public function category() {
        $this->response->addHeader(\'Content-Type: application/json\');
        $this->response->setOutput(json_encode(array("Category API not implemented", false)));
    }
}
?>';

if (file_put_contents($api_source, $fixed_api_content)) {
    echo "✓ Fixed API controller deployed\n";
} else {
    echo "❌ Failed to deploy API controller\n";
}

echo "\n3. Creating fixed webservices model...\n";

$fixed_model_content = '<?php
################################################################################################
# Fixed Webservices Model - Enhanced with mapping creation
################################################################################################

class ModelCatalogWkWebservicesTab extends Model {

    private function logDebug($message) {
        $log_message = date(\'Y-m-d H:i:s\') . " - FIXED MODEL: " . $message . "\\n";
        file_put_contents(DIR_SYSTEM . \'../model_debug.log\', $log_message, FILE_APPEND);
        error_log("FIXED MODEL: " . $message);
    }

    public function userValidation($data){
        $sql ="SELECT api_id as id FROM `" . DB_PREFIX . "api` WHERE `key` = \'" . $this->db->escape($data[\'api_key\']) . "\' AND status = \'1\'";
        $result=$this->db->query($sql);
        if(isset($result->row[\'id\'])) {
            $Authkey = $this->CreateSessionKey($result->row[\'id\']);
            return $Authkey;
        } else {
            return false;
        }
    }

    public function CreateSessionKey($id){
        $date = date("Y-m-d H:i:s");
        $seed = str_split(\'abcdefghijklmnopqrstuvwxyz\'
                             .\'ABCDEFGHIJKLMNOPQRSTUVWXYZ\'
                             .\'0123456789\');
        shuffle($seed);
        $rand = \'\';
        foreach (array_rand($seed, 32) as $k) $rand .= $seed[$k];

        $sql =$this->db->query("SELECT id FROM " . DB_PREFIX . "api_keys  WHERE user_id=\'".$id."\'");

        if(isset($sql->row[\'id\'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "api_keys SET date_created=\'$date\' ,Auth_key=\'$rand\' WHERE id=\'".$sql->row[\'id\']."\'");
        } else {
            $this->db->query("INSERT INTO " . DB_PREFIX . "api_keys SET user_id=\'" .$id. "\', date_created=\'$date\' ,Auth_key=\'$rand\'");
        }
        return $rand;
    }

    public function addProduct($data) {
        $this->logDebug("Starting addProduct with: " . json_encode($data));
        
        $data[\'created_by\'] = isset($data[\'created_by\']) ? $data[\'created_by\'] : \'From Odoo\';
        $merge_data = array();
        
        try {
            // Product insertion
            $sql = "INSERT INTO " . DB_PREFIX . "product SET " .
                   "model = \'" . $this->db->escape($data[\'model\']) . "\', " .
                   "sku = \'" . $this->db->escape($data[\'sku\']) . "\', " .
                   "location = \'" . $this->db->escape($data[\'location\']) . "\', " .
                   "quantity = \'" . (int)$data[\'quantity\'] . "\', " .
                   "minimum = \'" . (int)$data[\'minimum\'] . "\', " .
                   "subtract = \'" . (int)$data[\'subtract\'] . "\', " .
                   "stock_status_id = \'" . (int)$data[\'stock_status_id\'] . "\', " .
                   "date_available = \'" . $this->db->escape($data[\'date_available\']) . "\', " .
                   "manufacturer_id = \'" . (int)$data[\'manufacturer_id\'] . "\', " .
                   "shipping = \'" . (int)$data[\'shipping\'] . "\', " .
                   "price = \'" . (float)$data[\'price\'] . "\', " .
                   "points = \'" . (int)$data[\'points\'] . "\', " .
                   "weight = \'" . (float)$data[\'weight\'] . "\', " .
                   "weight_class_id = \'" . (int)$data[\'weight_class_id\'] . "\', " .
                   "length = \'" . (float)$data[\'length\'] . "\', " .
                   "width = \'" . (float)$data[\'width\'] . "\', " .
                   "height = \'" . (float)$data[\'height\'] . "\', " .
                   "length_class_id = \'" . (int)$data[\'length_class_id\'] . "\', " .
                   "status = \'" . (int)$data[\'status\'] . "\', " .
                   "tax_class_id = \'" . (int)$data[\'tax_class_id\'] . "\', " .
                   "sort_order = \'" . (int)$data[\'sort_order\'] . "\', " .
                   "date_added = NOW()";
            
            $this->logDebug("Executing product insert");
            $this->db->query($sql);
            $product_id = $this->db->getLastId();
            
            $this->logDebug("Product created with ID: " . $product_id);

            // Product description
            foreach ($data[\'product_description\'] as $language_id => $value) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET " .
                               "product_id = \'" . (int)$product_id . "\', " .
                               "language_id = \'" . (int)$language_id . "\', " .
                               "name = \'" . $this->db->escape($value[\'name\']) . "\', " .
                               "meta_keyword = \'" . $this->db->escape($value[\'meta_keyword\']) . "\', " .
                               "meta_description = \'" . $this->db->escape($value[\'meta_description\']) . "\', " .
                               "description = \'" . $this->db->escape($value[\'description\']) . "\'");
            }

            // Product to store
            if (isset($data[\'product_store\'])) {
                foreach ($data[\'product_store\'] as $store_id) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = \'" . (int)$product_id . "\', store_id = \'" . (int)$store_id . "\'");
                }
            }

            // Product categories
            if (isset($data[\'product_category\']) && is_array($data[\'product_category\'])) {
                foreach ($data[\'product_category\'] as $category_id) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = \'" . (int)$product_id . "\', category_id = \'" . (int)$category_id . "\'");
                }
            }

            // CRITICAL: Create ERP template mapping
            if (isset($data[\'erp_template_id\']) && $data[\'erp_template_id\']) {
                $erp_template_id = (int)$data[\'erp_template_id\'];
                $mapping_sql = "INSERT INTO " . DB_PREFIX . "erp_product_template_merge SET " .
                              "erp_template_id = \'" . $erp_template_id . "\', " .
                              "opencart_product_id = \'" . (int)$product_id . "\', " .
                              "created_by = \'" . $this->db->escape($data[\'created_by\']) . "\'";
                
                $this->logDebug("Creating mapping: " . $mapping_sql);
                $this->db->query($mapping_sql);
                $this->logDebug("Template mapping created successfully");
            }

            return array(
                \'product_id\' => $product_id,
                \'merge_data\' => $merge_data,
            );

        } catch (Exception $e) {
            $this->logDebug("Exception: " . $e->getMessage());
            throw $e;
        }
    }

    // Other required methods
    public function updateProductStock($params) {
        $status = $params[\'stock\'] > 0 ? 7 : 5;
        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = \'" . (int)$params[\'stock\'] . "\', stock_status_id = \'".$status."\', date_modified = NOW() WHERE product_id = \'".$params[\'product_id\']."\'");
    }

    public function addCategory($data) { return 0; }
    public function editProduct($product_id, $data) { return array(\'product_id\' => $product_id, \'merge_data\' => array()); }
    public function addOrderHistory($order_id, $data) { return false; }
    public function getProductOptionId($product_id) { return \'\'; }
    public function getProductOptionValueId($product_id, $option_value_id) { return \'\'; }
}
?>';

if (file_put_contents($model_source, $fixed_model_content)) {
    echo "✓ Fixed model deployed\n";
} else {
    echo "❌ Failed to deploy model\n";
}

echo "\n4. Setting file permissions...\n";
if (file_exists($api_source)) {
    chmod($api_source, 0644);
    echo "✓ API controller permissions set\n";
}
if (file_exists($model_source)) {
    chmod($model_source, 0644);
    echo "✓ Model permissions set\n";
}

echo "\n=== DEPLOYMENT COMPLETE ===\n";
echo "\nNEXT STEPS:\n";
echo "1. Try syncing a product from Odoo\n";
echo "2. Check debug logs: oob_fixed_debug.log and model_debug.log\n";
echo "3. Verify product appears in OpenCart admin\n";
echo "4. Check mapping interface\n";
?>