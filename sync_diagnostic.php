<?php
// Diagnostic script to check product sync status
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== PRODUCT SYNC DIAGNOSTIC ===\n\n";

// Check 1: Recent products created today
echo "1. Products created in last 24 hours:\n";
$yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
$result = $mysqli->query("SELECT p.product_id, p.model, p.date_added, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE p.date_added > '$yesterday' AND pd.language_id = 5 ORDER BY p.date_added DESC LIMIT 10");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Created: {$row['date_added']}\n";
    }
} else {
    echo "❌ No products created in last 24 hours\n";
}

// Check 2: Recent mappings created
echo "\n2. Recent mappings in last 24 hours:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id IN (SELECT product_id FROM oc_product WHERE date_added > '$yesterday') ORDER BY opencart_product_id DESC");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ Mapping: OpenCart ID {$row['opencart_product_id']} → ERP Template {$row['erp_template_id']}, Created by: {$row['created_by']}\n";
    }
} else {
    echo "❌ No new mappings created in last 24 hours\n";
}

// Check 3: API Keys status
echo "\n3. API Keys status:\n";
$result = $mysqli->query("SELECT * FROM oc_api_keys ORDER BY date_created DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $time_ago = time() - strtotime($row['date_created']);
        echo "✓ API Key ID {$row['id']}, User ID: {$row['user_id']}, Last used: " . round($time_ago/3600, 1) . " hours ago\n";
    }
} else {
    echo "❌ No API keys found\n";
}

// Check 4: Test if specific products exist that Odoo thinks were created
echo "\n4. Checking for products Odoo thinks were created:\n";
$test_products = [2953, 2954, 2955]; // Based on previous logs

foreach ($test_products as $product_id) {
    $result = $mysqli->query("SELECT p.product_id, p.model, p.status, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE p.product_id = $product_id AND pd.language_id = 5");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✓ Product $product_id EXISTS: {$row['name']}, Status: {$row['status']}\n";
        
        // Check mapping
        $mapping = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = $product_id");
        if ($mapping && $mapping->num_rows > 0) {
            $map_row = $mapping->fetch_assoc();
            echo "  ✓ Has mapping to ERP template: {$map_row['erp_template_id']}\n";
        } else {
            echo "  ❌ No mapping found\n";
        }
    } else {
        echo "❌ Product $product_id NOT found\n";
    }
}

// Check 5: Files status
echo "\n5. Checking if fixed files are deployed:\n";

// Check if we can find evidence of fixed files
if (file_exists(DIR_SYSTEM . '../oob_fixed_debug.log')) {
    $log_content = file_get_contents(DIR_SYSTEM . '../oob_fixed_debug.log');
    echo "✓ Fixed debug log exists, last few lines:\n";
    $lines = explode("\n", trim($log_content));
    $recent_lines = array_slice($lines, -5);
    foreach ($recent_lines as $line) {
        echo "  " . $line . "\n";
    }
} else {
    echo "❌ Fixed debug log not found - fixed files may not be deployed\n";
}

// Check 6: Categories that products are trying to use
echo "\n6. Checking category assignments:\n";
$result = $mysqli->query("SELECT DISTINCT pc.category_id, c.status, cd.name FROM oc_product_to_category pc LEFT JOIN oc_category c ON pc.category_id = c.category_id LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id WHERE pc.product_id IN (SELECT product_id FROM oc_product WHERE date_added > '$yesterday') AND cd.language_id = 5");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Category {$row['category_id']}: {$row['name']}, Status: {$row['status']}\n";
    }
} else {
    echo "No category assignments for recent products\n";
}

$mysqli->close();
echo "\n=== DIAGNOSTIC COMPLETE ===\n";
?>