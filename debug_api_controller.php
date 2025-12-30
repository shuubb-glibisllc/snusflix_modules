<?php
################################################################################################
# DEBUG OpenCart API Controller - Enhanced Debug Logging
################################################################################################

$ADMIN_PATH = DIR_SYSTEM . '../admin/';
require_once ($ADMIN_PATH . 'model/catalog/wk_webservices_tab.php');

class ControllerApiOob extends Controller {
    
    private function logDebug($message) {
        $log = date('Y-m-d H:i:s') . " [DEBUG-API] " . $message . "\n";
        file_put_contents('sync_debug.log', $log, FILE_APPEND | LOCK_EX);
        error_log("DEBUG-API: " . $message);
    }

    public function login(){
        $message = '';
        $status = False;
        $params = $this->request->post;

        $raw_data = json_decode(file_get_contents("php://input"), true);
        if ($raw_data) {
            foreach ($raw_data as $key => $value) {
                $params[$key] = $value;
            }
        }

        if (isset($params) && isset($params['api_key']) && trim($params['api_key'])) {
            $api_key = $params['api_key'];
            $username = 'demo';
            $password = '';

            $this->load->model('catalog/wkodoo');
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('wkconnector');

            if($this->request->server['REQUEST_METHOD'] == 'POST') {
                $response = $this->model_catalog_wkodoo->userValidation(array('api_key' => $api_key));
                if ($response !== false) {
                    $message = "Webservices key is working";
                    $status = true;
                    $url = $this->config->get('config_url');
                    $url_array = parse_url($url);
                    $session = array(
                        "url" => $url,
                        "session_key" => $response,
                        "domain" => $url_array['host'],
                    );
                } else {
                    $message = "Unable to login, please check API key";
                }
            } else {
                $message = "Please use POST request";
            }
        } else {
            $message = "API key is required";
        }

        header('Content-Type: application/json');
        $response = json_encode(array($message, $session ?? '', $status));
        echo $response;
        die;
    }

    public function product() {
        $this->logDebug("=== PRODUCT API CALLED ===");
        
        $connection = false;
        $message = '';
        $last_id = 0;
        $status = False;
        $session = '';
        $params = $this->request->post;

        // Get raw JSON data
        $raw_data = json_decode(file_get_contents("php://input"), true);
        if ($raw_data) {
            $params = array_merge($params, $raw_data);
        }

        $this->logDebug("Received data: " . json_encode($params));

        // Extract session
        if (isset($params['session'])) {
            $session = $params['session'];
            unset($params['session']);
        }
        if (isset($this->request->get['session'])) {
            $session = $this->request->get['session'];
        }

        $this->logDebug("Session key: " . $session);

        $connection = $this->Validate_session_key($session);
        $this->logDebug("Connection validation result: " . ($connection[0] ? 'SUCCESS' : 'FAILED'));

        if ($connection[0]) {
            // *** PRODUCT UPDATE MODE *** (when product_id is present)
            if (!empty($params) && isset($params['product_id'])) {
                $this->logDebug("ENTERING UPDATE MODE - Product ID: " . $params['product_id']);
                
                $product_id = (int)$params['product_id'];
                
                // Validation
                if ($product_id <= 0) {
                    $message = 'Invalid product_id';
                    $this->logDebug("ERROR: Invalid product_id: " . $product_id);
                } elseif (!isset($params['name'])) {
                    $message = 'Missing product name';
                    $this->logDebug("ERROR: Missing product name");
                } else {
                    $this->logDebug("UPDATE validation passed");
                }

                if ($message == '') {
                    try {
                        $this->logDebug("Creating ModelCatalogWkWebservicesTab instance...");
                        $obj = new ModelCatalogWkWebservicesTab($this->registry);
                        $this->logDebug("Model instance created successfully");
                        
                        // Check if editProduct method exists
                        if (method_exists($obj, 'editProduct')) {
                            $this->logDebug("editProduct method EXISTS");
                        } else {
                            $this->logDebug("ERROR: editProduct method DOES NOT EXIST");
                            $message = "editProduct method not found";
                        }
                        
                        if ($message == '') {
                            // Prepare update data
                            $language_id = $this->config->get('config_language_id') ?: 1;
                            $this->logDebug("Language ID: " . $language_id);
                            
                            $data = array(
                                'model' => $params['model'] ?? '',
                                'sku' => $params['sku'] ?? '',
                                'quantity' => (int)($params['quantity'] ?? 0),
                                'price' => (float)($params['price'] ?? 0),
                                'weight' => (float)($params['weight'] ?? 0),
                                'status' => (int)($params['status'] ?? 1),
                                'stock_status_id' => ($params['quantity'] ?? 0) > 0 ? 7 : 5,
                                'erp_product_id' => (int)($params['erp_product_id'] ?? 0),
                                'erp_template_id' => (int)($params['erp_template_id'] ?? $params['erp_product_id'] ?? 0),
                                'variant_id' => $params['variant_id'] ?? null,
                                'created_by' => 'From Odoo Update',
                                'product_description' => array(
                                    $language_id => array(
                                        'name' => $params['name'],
                                        'description' => $params['description'] ?? '',
                                        'meta_keyword' => $params['meta_keyword'] ?? '',
                                        'meta_description' => $params['meta_description'] ?? '',
                                        'tag' => $params['tag'] ?? ''
                                    )
                                )
                            );

                            // Add categories if provided
                            if (isset($params['product_category']) && is_array($params['product_category'])) {
                                $data['product_category'] = $params['product_category'];
                            }

                            $this->logDebug("Prepared update data: " . json_encode($data));
                            $this->logDebug("Calling editProduct method...");
                            
                            $result = $obj->editProduct($product_id, $data);
                            $this->logDebug("editProduct returned: " . json_encode($result));
                            
                            if ($result && isset($result['product_id'])) {
                                $last_id = $result['product_id'];
                                $merge_data = $result['merge_data'] ?? array();
                                $status = true;
                                $message = "Product successfully updated. Product Id: " . $last_id;
                                $this->logDebug("SUCCESS: Product updated with ID " . $last_id);
                            } else {
                                $message = "Failed to update product - editProduct returned false";
                                $this->logDebug("ERROR: editProduct returned false or invalid result");
                            }
                        }
                        
                    } catch (Exception $e) {
                        $message = "Update error: " . $e->getMessage();
                        $this->logDebug("EXCEPTION in product update: " . $e->getMessage());
                        $this->logDebug("Exception trace: " . $e->getTraceAsString());
                    } catch (Error $e) {
                        $message = "Fatal error: " . $e->getMessage();
                        $this->logDebug("FATAL ERROR in product update: " . $e->getMessage());
                        $this->logDebug("Error trace: " . $e->getTraceAsString());
                    }
                }
                
            } else {
                $this->logDebug("ENTERING CREATE MODE or INVALID DATA");
                $message = "No product_id provided - would create product";
            }
        } else {
            $message = "Authentication failed";
            $this->logDebug("ERROR: Authentication failed");
        }

        $this->logDebug("Final message: " . $message);
        $this->logDebug("Final status: " . ($status ? 'true' : 'false'));

        $response = array($message, array("product_id" => $last_id, "merge_data" => array()), $status);
        $this->logDebug("Final response: " . json_encode($response));
        
        header('Content-Type: application/json');
        echo json_encode($response);
        die;
    }

    public function Validate_session_key($key){
        $status = false;
        $connection = array();

        if (isset($key) && trim($key)) {
            $this->load->model('catalog/wkodoo');
            $result = $this->model_catalog_wkodoo->keyValidation($key);
            if ($result) {
                $status = true;
            }
        }
        $connection[0] = $status;
        return $connection;
    }
}
?>