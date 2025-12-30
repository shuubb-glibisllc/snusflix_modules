<?php
// Check available categories in OpenCart
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== AVAILABLE OPENCART CATEGORIES ===\n\n";

$result = $mysqli->query("SELECT c.category_id, c.status, cd.name FROM oc_category c LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id WHERE cd.language_id = 5 ORDER BY c.category_id");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Category ID: {$row['category_id']}, Status: {$row['status']}, Name: {$row['name']}\n";
    }
} else {
    echo "No categories found!\n";
}

echo "\n=== MAPPING TABLE CHECK FOR PRODUCT 2955 ===\n";

// Check if product 2955 exists in mapping table
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "FOUND MAPPING: OpenCart Product ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No ERP template mapping found for product 2955!\n";
}

// Check if product 2955 exists in products table
echo "\nProduct 2955 in oc_product table:\n";
$result = $mysqli->query("SELECT product_id, model, sku, status FROM oc_product WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, Status: {$row['status']}\n";
    }
} else {
    echo "Product 2955 NOT found in oc_product table!\n";
}

// Show recent mappings
echo "\nRecent mappings (last 5):\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge ORDER BY opencart_product_id DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No mappings found!\n";
}

echo "\n=== PRODUCT EXISTENCE CHECK ===\n";

// Check for TEEEEEEST product
echo "\n1. Searching for 'TEEEEEEST' product:\n";
$result = $mysqli->query("SELECT p.product_id, p.model, p.sku, p.status, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE pd.name LIKE '%TEEEEEEST%' OR p.model LIKE '%TEEEEEEST%' OR p.sku LIKE '%TEEEEEEST%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "FOUND: Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}\n";
    }
} else {
    echo "No products found with 'TEEEEEEST'\n";
}

// Check recent products created today
echo "\n2. Products created today:\n";
$today = date('Y-m-d');
$result = $mysqli->query("SELECT p.product_id, p.model, p.status, p.date_added, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE DATE(p.date_added) = '$today' AND pd.language_id = 5 ORDER BY p.date_added DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " products created today:\n";
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}, Date: {$row['date_added']}\n";
    }
} else {
    echo "No products created today\n";
}

// Check last 5 products created
echo "\n3. Last 5 products created:\n";
$result = $mysqli->query("SELECT p.product_id, p.model, p.sku, p.status, p.date_added, pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id WHERE pd.language_id = 5 ORDER BY p.date_added DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, Name: {$row['name']}, Status: {$row['status']}, Date: {$row['date_added']}\n";
    }
} else {
    echo "No recent products found\n";
}

$mysqli->close();
?>