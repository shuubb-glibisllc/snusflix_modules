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

echo "\n=== QUICK FIX: Assign product to existing category ===\n";
echo "We can reassign product 2950 to an existing category instead of 889\n";

$mysqli->close();
?>