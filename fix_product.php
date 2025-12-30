<?php
// Quick fix for product 2950 - assign to valid category
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== FIXING PRODUCT 2950 CATEGORY ===\n\n";

// Find the first available category
$result = $mysqli->query("SELECT category_id FROM oc_category WHERE status = 1 ORDER BY category_id LIMIT 1");
$valid_category = null;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $valid_category = $row['category_id'];
    echo "Found valid category ID: $valid_category\n";
} else {
    echo "No valid categories found! Creating default category...\n";
    
    // Create a default category if none exists
    $mysqli->query("INSERT INTO oc_category (parent_id, top, column_width, sort_order, status, date_added, date_modified) VALUES (0, 1, 1, 1, 1, NOW(), NOW())");
    $valid_category = $mysqli->insert_id;
    
    // Add category description
    $mysqli->query("INSERT INTO oc_category_description (category_id, language_id, name, description, meta_title, meta_description, meta_keyword) VALUES ($valid_category, 5, 'Default Category', 'Default category for products', 'Default Category', '', '')");
    
    echo "Created default category ID: $valid_category\n";
}

if ($valid_category) {
    // Update product category assignment
    $mysqli->query("DELETE FROM oc_product_to_category WHERE product_id = 2950");
    $mysqli->query("INSERT INTO oc_product_to_category (product_id, category_id) VALUES (2950, $valid_category)");
    
    echo "Updated product 2950 category assignment to: $valid_category\n";
    echo "Product should now be visible in OpenCart!\n";
    
    // Verify the fix
    $result = $mysqli->query("SELECT * FROM oc_product_to_category WHERE product_id = 2950");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Verification: Product 2950 is now assigned to category {$row['category_id']}\n";
    }
} else {
    echo "Failed to fix category assignment!\n";
}

$mysqli->close();
echo "\n=== FIX COMPLETE ===\n";
?>