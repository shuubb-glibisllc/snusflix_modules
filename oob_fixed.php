<?php
################################################################################################
# Fixed Webservices API Controller for OpenCart 3.x.x.x - OOB Bridge
################################################################################################

$ADMIN_PATH = DIR_SYSTEM . '../admin/';
require_once ($ADMIN_PATH . 'model/catalog/wk_webservices_tab.php');

class ControllerApiOob extends Controller {
    
    private function logDebug($message) {
        $log_message = date('Y-m-d H:i:s') . " - FIXED OOB DEBUG: " . $message . "\n";
        file_put_contents(DIR_SYSTEM . '../oob_fixed_debug.log', $log_message, FILE_APPEND);
        error_log("FIXED OOB DEBUG: " . $message);
    }

    public function login(){
        $message = '';
        $status = False;
        $params = $this->request->post;

        //Accepting data in json format / raw data
        $raw_data = json_decode(file_get_contents("php://input"), true);
        if ($raw_data) {
            foreach ($raw_data as $key => $value) {
                $params[$key] = $value;
            }
        }

        if (isset($params) && isset($params['api_key']) && trim($params['api_key'])) {
            $obj = new ModelCatalogWkWebservicesTab($this->registry);
            $total = $obj->userValidation($params);

            if ($total) {
                $message = $total;
                $status = True;
            } else {
                $message = 'Details - api_user and api_key are not correct !!!';
            }
        } else {
            $message ='Method must have a parameter(array type). Array must contains api_user and api_key!!!';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array($message, $status)));
    }

    public function Validate_session_key($session){
        $connection = false;
        $message =  '';
        if (!is_string($session)) {
            $message = 'First parameter is reserved for session key, and it should be string type!!!';
        } elseif (isset($session) && trim($session)) {
            $sql ="SELECT id FROM " . DB_PREFIX . "api_keys  WHERE Auth_key='".$session."'";
            $result=$this->db->query($sql);

            if (isset($result->row['id'])) {
                $connection = true;
            } else {
                $message = 'Session Key is not validated!!!';
            }
        } else {
            $message = 'Method must have a parameter(string type)!!!';
        }

        return array($connection, $message);
    }

    public function product() {
        $connection = false;
        $message = '';
        $last_id = 0;
        $status = False;
        $session = '';
        $params = $this->request->post;

        // Enhanced debug logging
        $this->logDebug("Product API called with method: " . $_SERVER['REQUEST_METHOD']);
        
        //Accepting data in json format / raw data
        $raw_data = json_decode(file_get_contents("php://input"), true);
        if ($raw_data) {
            foreach ($raw_data as $key => $value) {
                $params[$key] = $value;
            }
        }

        $this->logDebug("Raw input data: " . json_encode($raw_data));
        $this->logDebug("Final params: " . json_encode($params));

        if (isset($params['session']) && $params['session']) {
            $session = $params['session'];
            unset($params['session']);
        }

        if (isset($this->request->get['session']) && $this->request->get['session']) {
            $session = $this->request->get['session'];
        }

        $connection = $this->Validate_session_key($session);

        if ($connection[0]) {
            //Edit product
            if (isset($params['product_id']) && $params['product_id']) {
                $this->logDebug("Edit product mode for ID: " . $params['product_id']);
                // Edit product logic would go here
                $message = 'Product edit not implemented in this fixed version';
                
            //Add product
            } elseif (!empty($params)) {
                $this->logDebug("Add product mode initiated");
                $message = '';

                $obj = new ModelCatalogWkWebservicesTab($this->registry);

                // Enhanced validation with better error handling
                if (!isset($params['erp_product_id'])) {
                    $message = 'Parameter array must have erp product id for merge data !!!';
                    $this->logDebug("Validation failed: missing erp_product_id");
                } elseif (!isset($params['name'])) {
                    $message = 'Parameter array must have product name !!!';
                    $this->logDebug("Validation failed: missing name");
                } elseif (!isset($params['erp_template_id'])) {
                    $message = 'Parameter array must have erp template id for mapping !!!';
                    $this->logDebug("Validation failed: missing erp_template_id");
                }

                $language_id = $this->config->get('config_language_id');
                if (!$language_id && $message == '') {
                    $message = 'OpenCart Config Language Not Found!!!';
                    $this->logDebug("Validation failed: missing language_id");
                }

                $this->logDebug("Validation result: " . ($message == '' ? 'PASSED' : 'FAILED - ' . $message));

                // Enhanced default data structure
                $data = array (
                    'model' => isset($params['model']) ? $params['model'] : 'DEFAULT-' . time(),
                    'sku' => isset($params['sku']) ? $params['sku'] : '',
                    'location' => '',
                    'quantity' => isset($params['quantity']) ? (int)$params['quantity'] : 0,
                    'minimum' => 1,
                    'subtract' => 1,
                    'stock_status_id' => isset($params['quantity']) && $params['quantity'] > 0 ? 7 : 5,
                    'date_available' => date('Y-m-d'),
                    'manufacturer_id' => 0,
                    'shipping' => 1,
                    'price' => isset($params['price']) ? (float)$params['price'] : 0.00,
                    'points' => 0,
                    'weight' => isset($params['weight']) ? (float)$params['weight'] : 0.00,
                    'weight_class_id' => 1,
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'length_class_id' => 1,
                    'status' => 1,
                    'tax_class_id' => 0,
                    'sort_order' => 0,
                    'keyword' => '',
                    'erp_product_id' => isset($params['erp_product_id']) ? (int)$params['erp_product_id'] : 0,
                    'erp_template_id' => isset($params['erp_template_id']) ? (int)$params['erp_template_id'] : 0,
                    'created_by' => 'From Odoo Fixed',
                    'product_category' => array(),
                    'product_description' => array(
                        $language_id => array(
                            'name' => isset($params['name']) ? $params['name'] : 'Default Product',
                            'meta_keyword' => '',
                            'meta_description' => '',
                            'description' => isset($params['description']) ? $params['description'] : '',
                            'tag' => ''
                        )
                    ),
                );

                // Handle product categories properly
                if (isset($params['product_category']) && is_array($params['product_category'])) {
                    // Validate that categories exist before assigning
                    $valid_categories = array();
                    foreach ($params['product_category'] as $cat_id) {
                        $cat_check = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category WHERE category_id = " . (int)$cat_id . " AND status = 1");
                        if ($cat_check->num_rows > 0) {
                            $valid_categories[] = (int)$cat_id;
                            $this->logDebug("Category " . $cat_id . " validated successfully");
                        } else {
                            $this->logDebug("Category " . $cat_id . " is invalid or disabled");
                        }
                    }
                    
                    if (empty($valid_categories)) {
                        // Assign to first available category if none are valid
                        $default_cat = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category WHERE status = 1 ORDER BY category_id LIMIT 1");
                        if ($default_cat->num_rows > 0) {
                            $valid_categories[] = (int)$default_cat->row['category_id'];
                            $this->logDebug("Using default category: " . $default_cat->row['category_id']);
                        }
                    }
                    
                    $data['product_category'] = $valid_categories;
                }

                // Copy other parameters
                foreach ($params as $key => $value) {
                    if ($key == 'name' || $key == 'meta_keyword' || $key == 'meta_description' || $key == 'description' || $key == 'tag') {
                        $data['product_description'][$language_id][$key] = $params[$key];
                    } elseif (!isset($data[$key])) {
                        $data[$key] = $params[$key];
                    }
                }

                $data['product_store'] = array(0);

                $this->logDebug("Final data structure prepared for product creation");

                if ($message == '') {
                    try {
                        $this->logDebug("Starting product creation process");
                        $this->db->query("START TRANSACTION");
                        
                        $result = $obj->addProduct($data);
                        $this->logDebug("addProduct returned: " . json_encode($result));
                        
                        if ($result && isset($result['product_id']) && $result['product_id'] > 0) {
                            $last_id = $result;
                            $status = True;
                            
                            // Verify product was actually created
                            $verify = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$result['product_id']);
                            if ($verify->num_rows > 0) {
                                $this->logDebug("Product creation verified in database");
                                $this->db->query("COMMIT");
                                $message = 'Product successfully added. Product Id: ' . $result['product_id'];
                            } else {
                                $this->logDebug("Product creation failed - not found in database after creation");
                                $this->db->query("ROLLBACK");
                                $status = False;
                                $message = 'Product creation failed - database verification failed';
                            }
                        } else {
                            $this->logDebug("addProduct failed or returned invalid result");
                            $this->db->query("ROLLBACK");
                            $status = False;
                            $message = 'Product creation failed - addProduct returned error';
                        }
                        
                    } catch (Exception $e) {
                        $this->logDebug("Exception during product creation: " . $e->getMessage());
                        $this->db->query("ROLLBACK");
                        $status = False;
                        $message = 'Product creation failed with exception: ' . $e->getMessage();
                    }
                } else {
                    $this->logDebug("Validation failed, not attempting product creation");
                }

                $this->response->addHeader('Content-Type: application/json');
                $response_data = array($message, $last_id, $status);
                $this->logDebug("Final API response: " . json_encode($response_data));
                $this->response->setOutput(json_encode($response_data));
            } else {
                $message = 'No Params Find In The Url';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(array($message, 0)));
            }
        } else {
            //Get product
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array($connection[1], $last_id, $status)));
        }
    }

    // Add other required methods here (category, option, etc.)
    public function category() {
        // Simplified category method for now
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array("Category API not implemented in fixed version", false)));
    }
}
?>