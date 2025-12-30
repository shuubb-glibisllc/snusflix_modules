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

echo "=== OPENCART PRODUCT 2950 DEBUG ===\n\n";

// Check if product exists
echo "1. Product in oc_product:\n";
$result = $mysqli->query("SELECT product_id, model, sku, status, quantity FROM oc_product WHERE product_id = 2950");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, SKU: {$row['sku']}, Status: {$row['status']}, Quantity: {$row['quantity']}\n";
    }
} else {
    echo "Product 2950 NOT found in oc_product table!\n";
}

// Check store assignment
echo "\n2. Store assignment in oc_product_to_store:\n";
$result = $mysqli->query("SELECT * FROM oc_product_to_store WHERE product_id = 2950");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Store ID: {$row['store_id']}\n";
    }
} else {
    echo "No store assignment found for product 2950!\n";
}

// Check category assignment
echo "\n3. Category assignment in oc_product_to_category:\n";
$result = $mysqli->query("SELECT * FROM oc_product_to_category WHERE product_id = 2950");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Category ID: {$row['category_id']}\n";
    }
} else {
    echo "No category assignment found for product 2950!\n";
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
$result = $mysqli->query("SELECT product_id, language_id, name FROM oc_product_description WHERE product_id = 2950");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Language: {$row['language_id']}, Name: {$row['name']}\n";
    }
} else {
    echo "No product description found for product 2950!\n";
}

// Check ERP template mapping
echo "\n6. ERP template mapping:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2950");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "OpenCart Product ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No ERP template mapping found for product 2950!\n";
}

$mysqli->close();
echo "\n=== DEBUG COMPLETE ===\n";
?>