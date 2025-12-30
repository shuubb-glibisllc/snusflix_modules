<?php
// Check the latest product 2953 and its categories
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== CHECKING LATEST PRODUCT 2953 ===\n\n";

// Check if product exists
echo "1. Product 2953 details:\n";
$result = $mysqli->query("SELECT product_id, model, sku, status FROM oc_product WHERE product_id = 2953");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Model: {$row['model']}, Status: {$row['status']}\n";
    }
} else {
    echo "Product 2953 NOT found!\n";
}

// Check category assignments
echo "\n2. Category assignments for product 2953:\n";
$result = $mysqli->query("SELECT * FROM oc_product_to_category WHERE product_id = 2953");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Product ID: {$row['product_id']}, Category ID: {$row['category_id']}\n";
    }
} else {
    echo "No categories assigned to product 2953!\n";
}

// Check if categories 523 and 573 exist
echo "\n3. Checking if categories 523 and 573 exist:\n";
$categories = [523, 573];
foreach ($categories as $cat_id) {
    $result = $mysqli->query("SELECT c.category_id, c.status, cd.name FROM oc_category c LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id WHERE c.category_id = $cat_id AND cd.language_id = 5");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Category $cat_id EXISTS - Name: {$row['name']}, Status: {$row['status']}\n";
    } else {
        echo "Category $cat_id NOT FOUND!\n";
    }
}

$mysqli->close();
echo "\n=== CHECK COMPLETE ===\n";
?>