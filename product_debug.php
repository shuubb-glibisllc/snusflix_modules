<?php
// Web-accessible debug script for product 2950
header('Content-Type: text/plain');

// Database connection
$host = 'localhost';
$user = 'snuszibe_snusflix';
$pass = '5L?9GvirjwqL';
$db = 'snuszibe_snusflix';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== OPENCART SYNC DIAGNOSTIC ===\n\n";

// Check for recent sync attempts
echo "1. Products created in last 24 hours:\n";
$yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
$recent_result = $mysqli->query("SELECT p.product_id, p.model, p.date_added, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE p.date_added > '$yesterday' AND pd.language_id = 5 ORDER BY p.date_added DESC LIMIT 10");

if ($recent_result && $recent_result->num_rows > 0) {
    while ($row = $recent_result->fetch_assoc()) {
        echo "✓ Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Created: {$row['date_added']}\n";
    }
} else {
    echo "❌ No products created in last 24 hours\n";
}

echo "\n2. API Key activity:\n";
$api_result = $mysqli->query("SELECT * FROM oc_api_keys ORDER BY date_created DESC LIMIT 3");
if ($api_result && $api_result->num_rows > 0) {
    while ($row = $api_result->fetch_assoc()) {
        $time_ago = time() - strtotime($row['date_created']);
        echo "API Key ID {$row['id']}: Last used " . round($time_ago/3600, 1) . " hours ago\n";
    }
}

echo "\n=== SPECIFIC PRODUCT 2955 DEBUG ===\n\n";

// Check if product exists
echo "1. Product in oc_product:\n";
$result = $mysqli->query("SELECT product_id, model, sku, status, quantity FROM oc_product WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, SKU: {$row['sku']}, Status: {$row['status']}, Quantity: {$row['quantity']}\n";
    }
} else {
    echo "Product 2955 NOT found in oc_product table!\n";
}

// Check store assignment
echo "\n2. Store assignment in oc_product_to_store:\n";
$result = $mysqli->query("SELECT * FROM oc_product_to_store WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Store ID: {$row['store_id']}\n";
    }
} else {
    echo "No store assignment found for product 2955!\n";
}

// Check category assignment
echo "\n3. Category assignment in oc_product_to_category:\n";
$result = $mysqli->query("SELECT * FROM oc_product_to_category WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Category ID: {$row['category_id']}\n";
    }
} else {
    echo "No category assignment found for product 2955!\n";
}

// Check if category 889 exists
echo "\n4. Check if category 889 exists:\n";
$result = $mysqli->query("SELECT category_id, status FROM oc_category WHERE category_id = 889");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Category ID: {$row['category_id']}, Status: {$row['status']}\n";
    }
} else {
    echo "Category 889 NOT found!\n";
}

// Check product description
echo "\n5. Product description:\n";
$result = $mysqli->query("SELECT product_id, language_id, name FROM oc_product_description WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Language: {$row['language_id']}, Name: {$row['name']}\n";
    }
} else {
    echo "No product description found for product 2955!\n";
}

// Check ERP template mapping
echo "\n6. ERP template mapping for product 2955:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "OpenCart Product ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No ERP template mapping found for product 2955!\n";
}

// Check all recent mappings in table
echo "\n7. Recent mappings in oc_erp_product_template_merge table:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge ORDER BY opencart_product_id DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No mappings found in oc_erp_product_template_merge table!\n";
}

// Check table structure
echo "\n8. Table structure of oc_erp_product_template_merge:\n";
$result = $mysqli->query("DESCRIBE oc_erp_product_template_merge");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Column: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Default: {$row['Default']}\n";
    }
} else {
    echo "Table oc_erp_product_template_merge NOT found!\n";
}

$mysqli->close();
echo "\n=== DEBUG COMPLETE ===\n";
?>