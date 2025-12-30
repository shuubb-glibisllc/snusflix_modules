<?php
// Check mapping table for product 2955
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== MAPPING CHECK FOR PRODUCT 2955 ===\n\n";

// Check if product 2955 exists in OpenCart
echo "1. Product 2955 in oc_product:\n";
$result = $mysqli->query("SELECT product_id, model, sku, status, quantity FROM oc_product WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, SKU: {$row['sku']}, Status: {$row['status']}, Quantity: {$row['quantity']}\n";
    }
} else {
    echo "Product 2955 NOT found in oc_product table!\n";
}

// Check ERP template mapping for 2955
echo "\n2. ERP template mapping for product 2955:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "FOUND: OpenCart Product ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No ERP template mapping found for product 2955!\n";
}

// Check all recent mappings (last 10)
echo "\n3. Recent mappings in oc_erp_product_template_merge table (last 10):\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge ORDER BY opencart_product_id DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "Total mappings: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No mappings found in oc_erp_product_template_merge table!\n";
}

// Count total mappings
echo "\n4. Total count of mappings:\n";
$result = $mysqli->query("SELECT COUNT(*) as total FROM oc_erp_product_template_merge");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total mappings in table: {$row['total']}\n";
}

// Check if table exists and its structure
echo "\n5. Table structure:\n";
$result = $mysqli->query("SHOW TABLES LIKE 'oc_erp_product_template_merge'");
if ($result && $result->num_rows > 0) {
    echo "Table exists. Checking structure:\n";
    $desc_result = $mysqli->query("DESCRIBE oc_erp_product_template_merge");
    while ($row = $desc_result->fetch_assoc()) {
        echo "Column: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}\n";
    }
} else {
    echo "Table oc_erp_product_template_merge does NOT exist!\n";
}

$mysqli->close();
echo "\n=== CHECK COMPLETE ===\n";
?>