<?php
// Check if products are actually being created in OpenCart
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== CHECKING ACTUAL PRODUCT CREATION ===\n\n";

// Check for recent products with "TEEEEEEST" or similar test names
echo "1. Searching for products with 'TEEEEEEST':\n";
$result = $mysqli->query("SELECT p.product_id, p.model, p.sku, p.status, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE pd.name LIKE '%TEEEEEEST%' OR p.model LIKE '%TEEEEEEST%' OR p.sku LIKE '%TEEEEEEST%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Found: Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}\n";
    }
} else {
    echo "No products found with 'TEEEEEEST'\n";
}

// Check for products with "Ref Odoo 1115" (the recent sync)
echo "\n2. Searching for products with 'Ref Odoo 1115':\n";
$result = $mysqli->query("SELECT p.product_id, p.model, p.sku, p.status, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE p.model LIKE '%Ref Odoo 1115%' OR pd.name LIKE '%1115%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Found: Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}\n";
    }
} else {
    echo "No products found with 'Ref Odoo 1115'\n";
}

// Check the most recently created products
echo "\n3. Last 10 products created in OpenCart:\n";
$result = $mysqli->query("SELECT p.product_id, p.model, p.sku, p.status, p.date_added, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE pd.language_id = 5 ORDER BY p.date_added DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}, Date: {$row['date_added']}\n";
    }
} else {
    echo "No recent products found\n";
}

// Check for any products created today
echo "\n4. Products created today:\n";
$today = date('Y-m-d');
$result = $mysqli->query("SELECT p.product_id, p.model, p.status, p.date_added, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE DATE(p.date_added) = '$today' AND pd.language_id = 5 ORDER BY p.date_added DESC");
if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " products created today:\n";
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}, Date: {$row['date_added']}\n";
    }
} else {
    echo "No products created today\n";
}

$mysqli->close();
echo "\n=== CHECK COMPLETE ===\n";
?>