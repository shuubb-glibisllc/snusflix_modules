<?php
// Verify if fixed files are deployed
header('Content-Type: text/plain');

echo "=== DEPLOYMENT VERIFICATION ===\n\n";

// Check 1: Look for fixed file signatures
echo "1. Checking for fixed file deployment:\n";

$api_file = DIR_SYSTEM . '../catalog/controller/api/oob.php';
$model_file = DIR_SYSTEM . '../admin/model/catalog/wk_webservices_tab.php';

if (file_exists($api_file)) {
    $api_content = file_get_contents($api_file);
    if (strpos($api_content, 'FIXED OOB DEBUG') !== false) {
        echo "✓ Fixed API controller is deployed\n";
    } else {
        echo "❌ OLD API controller is still active\n";
    }
    
    if (strpos($api_content, 'logDebug') !== false) {
        echo "✓ Enhanced logging is available\n";
    } else {
        echo "❌ No enhanced logging found\n";
    }
} else {
    echo "❌ API controller file not found\n";
}

if (file_exists($model_file)) {
    $model_content = file_get_contents($model_file);
    if (strpos($model_content, 'FIXED MODEL DEBUG') !== false) {
        echo "✓ Fixed model is deployed\n";
    } else {
        echo "❌ OLD model is still active\n";
    }
    
    if (strpos($model_content, 'erp_product_template_merge') !== false) {
        echo "✓ Template mapping logic is present\n";
    } else {
        echo "❌ Template mapping logic MISSING\n";
    }
} else {
    echo "❌ Model file not found\n";
}

// Check 2: Test basic functionality
echo "\n2. Testing basic API functionality:\n";

// Try to create a debug log entry
$debug_file = DIR_SYSTEM . '../deployment_test.log';
$test_message = date('Y-m-d H:i:s') . " - Deployment verification test\n";
if (file_put_contents($debug_file, $test_message, FILE_APPEND)) {
    echo "✓ File write permissions working\n";
} else {
    echo "❌ File write permissions failed\n";
}

// Check 3: Database connectivity
echo "\n3. Testing database connectivity:\n";
$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    echo "❌ Database connection failed\n";
} else {
    echo "✓ Database connection successful\n";
    
    // Test mapping table
    $result = $mysqli->query("SHOW TABLES LIKE 'oc_erp_product_template_merge'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Mapping table exists\n";
    } else {
        echo "❌ Mapping table missing\n";
    }
    
    $mysqli->close();
}

echo "\n=== VERIFICATION COMPLETE ===\n";

// Instructions
echo "\nNEXT STEPS:\n";
echo "1. If files show as OLD - upload the fixed versions\n";
echo "2. If files show as FIXED - try a new product sync from Odoo\n";
echo "3. Monitor logs for any new activity\n";
?>