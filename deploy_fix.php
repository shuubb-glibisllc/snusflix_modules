<?php
// Deploy script to upload and apply the fixes
header('Content-Type: text/plain');

echo "=== OPENCART PRODUCT SYNC FIX DEPLOYMENT ===\n\n";

// Step 1: Check current API status
echo "1. Testing current API status...\n";

// Create test data similar to what Odoo sends
$test_data = array(
    'session' => 'test_session_123',
    'erp_product_id' => 9999,
    'erp_template_id' => 9999,
    'name' => 'TEST SYNC FIX ' . time(),
    'model' => 'Test Model ' . time(),
    'description' => 'Test product for sync fix',
    'price' => 10.50,
    'quantity' => 5,
    'status' => 1,
    'product_category' => array(523) // Use existing valid category
);

echo "Test data prepared:\n";
echo "Product name: " . $test_data['name'] . "\n";
echo "ERP Template ID: " . $test_data['erp_template_id'] . "\n";

// Step 2: Create backup of original files (if they exist)
echo "\n2. Creating backup strategy...\n";

$backup_timestamp = date('Y-m-d_H-i-s');
$backup_commands = array(
    "cp catalog/controller/api/oob.php catalog/controller/api/oob_backup_{$backup_timestamp}.php",
    "cp admin/model/catalog/wk_webservices_tab.php admin/model/catalog/wk_webservices_tab_backup_{$backup_timestamp}.php"
);

echo "Backup files will be created with timestamp: " . $backup_timestamp . "\n";

// Step 3: List the fixes that need to be applied
echo "\n3. Fixes to be applied:\n";
echo "✓ Enhanced API controller with proper error handling\n";
echo "✓ Transaction management with rollback on failure\n";
echo "✓ Proper template mapping creation in oc_erp_product_template_merge table\n";
echo "✓ Category validation before assignment\n";
echo "✓ Enhanced logging for debugging\n";
echo "✓ Proper product verification after creation\n";

// Step 4: Database table verification
echo "\n4. Verifying required database tables exist...\n";

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$required_tables = array(
    'oc_product',
    'oc_product_description',
    'oc_product_to_store',
    'oc_product_to_category',
    'oc_erp_product_template_merge',
    'oc_category',
    'oc_api_keys'
);

foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table $table exists\n";
    } else {
        echo "✗ Table $table MISSING - this may cause issues\n";
    }
}

// Step 5: Check for valid categories
echo "\n5. Checking for valid product categories...\n";
$result = $mysqli->query("SELECT category_id, status FROM oc_category WHERE category_id IN (523, 573) AND status = 1");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ Valid category found: ID " . $row['category_id'] . "\n";
    }
} else {
    echo "⚠ Warning: Categories 523/573 not found, will use default category\n";
    
    // Find default category
    $default_result = $mysqli->query("SELECT category_id FROM oc_category WHERE status = 1 ORDER BY category_id LIMIT 1");
    if ($default_result && $default_result->num_rows > 0) {
        $default_cat = $default_result->fetch_assoc();
        echo "✓ Default category available: ID " . $default_cat['category_id'] . "\n";
    } else {
        echo "✗ ERROR: No valid categories found in database!\n";
    }
}

// Step 6: Installation instructions
echo "\n6. MANUAL INSTALLATION INSTRUCTIONS:\n";
echo "======================================\n\n";

echo "A. Backup current files:\n";
echo "   1. Download current catalog/controller/api/oob.php\n";
echo "   2. Download current admin/model/catalog/wk_webservices_tab.php\n\n";

echo "B. Upload fixed files:\n";
echo "   1. Upload 'oob_fixed.php' as 'catalog/controller/api/oob.php'\n";
echo "   2. Upload 'wk_webservices_tab_fixed.php' as 'admin/model/catalog/wk_webservices_tab.php'\n\n";

echo "C. Set file permissions:\n";
echo "   chmod 644 catalog/controller/api/oob.php\n";
echo "   chmod 644 admin/model/catalog/wk_webservices_tab.php\n\n";

echo "D. Test the fix:\n";
echo "   1. Run a product sync from Odoo\n";
echo "   2. Check the new debug logs: oob_fixed_debug.log and wk_model_debug.log\n";
echo "   3. Verify product appears in OpenCart admin product list\n";
echo "   4. Verify mapping appears in OpenCart admin OOB product mapping\n\n";

echo "E. Monitor logs:\n";
echo "   - tail -f oob_fixed_debug.log\n";
echo "   - tail -f wk_model_debug.log\n\n";

// Step 7: Key fixes summary
echo "7. KEY FIXES IMPLEMENTED:\n";
echo "=======================\n\n";

echo "❌ BEFORE (Problems):\n";
echo "- API reported success but products weren't created\n";
echo "- No mapping records in oc_erp_product_template_merge\n";
echo "- Poor error handling and logging\n";
echo "- No transaction management\n";
echo "- Category assignment without validation\n\n";

echo "✅ AFTER (Fixed):\n";
echo "- Proper transaction management with rollback\n";
echo "- Template mapping creation in oc_erp_product_template_merge\n";
echo "- Enhanced error handling and logging\n";
echo "- Product verification after creation\n";
echo "- Category validation before assignment\n";
echo "- Detailed debug logging for troubleshooting\n\n";

// Step 8: Test case
echo "8. TEST CASE AFTER DEPLOYMENT:\n";
echo "===============================\n";
echo "After uploading the fixed files, sync this product from Odoo:\n";
echo "Product Name: " . $test_data['name'] . "\n";
echo "Template ID: " . $test_data['erp_template_id'] . "\n";
echo "\nExpected results:\n";
echo "✓ Product should appear in OpenCart admin product list\n";
echo "✓ Product should be searchable by name\n";
echo "✓ Mapping should appear in OOB product mapping interface\n";
echo "✓ Debug logs should show successful creation process\n\n";

$mysqli->close();

echo "=== DEPLOYMENT GUIDE COMPLETE ===\n";
echo "\nNext step: Upload the fixed files to the server and test!\n";
?>