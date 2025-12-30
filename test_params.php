<?php
// Check what parameters are being sent to the API
header('Content-Type: text/plain');

echo "=== TESTING API PARAMETERS ===\n\n";

// Simulate the same data that Odoo would send
$test_data = array(
    'erp_product_id' => 1115,
    'erp_template_id' => 1115,
    'name' => 'Test Product',
    'model' => 'Ref Odoo 1115',
    'status' => 1,
    'product_category' => array(523, 573)
);

echo "Sample data that should be sent:\n";
echo print_r($test_data, true);

echo "\nChecking if erp_template_id exists: " . (isset($test_data['erp_template_id']) ? "YES - " . $test_data['erp_template_id'] : "NO") . "\n";
echo "Checking if erp_product_id exists: " . (isset($test_data['erp_product_id']) ? "YES - " . $test_data['erp_product_id'] : "NO") . "\n";

// Check the mapping logic
if (isset($test_data['erp_template_id'])) {
    echo "\nThe mapping creation logic SHOULD execute because erp_template_id is set\n";
    echo "SQL that should execute:\n";
    echo "INSERT INTO oc_erp_product_template_merge SET erp_template_id = '" . (int)$test_data['erp_template_id'] . "', opencart_product_id = 'PRODUCT_ID', created_by = 'From Odoo'\n";
} else {
    echo "\nThe mapping creation logic WILL NOT execute because erp_template_id is missing\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>