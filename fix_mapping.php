<?php
// Fix script to add mapping creation to the OpenCart product creation
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== MANUAL MAPPING FIX FOR PRODUCT 2955 ===\n\n";

// Check if product 2955 exists
$result = $mysqli->query("SELECT product_id FROM oc_product WHERE product_id = 2955");
if ($result && $result->num_rows > 0) {
    echo "✓ Product 2955 exists in OpenCart\n";
    
    // Check if mapping already exists
    $result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
    if ($result && $result->num_rows > 0) {
        echo "⚠ Mapping already exists for product 2955\n";
        $row = $result->fetch_assoc();
        echo "Existing mapping: OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    } else {
        echo "⚠ No mapping exists for product 2955, creating it...\n";
        
        // Create the mapping (using template ID 1115 based on previous logs)
        $erp_template_id = 1115;
        $query = "INSERT INTO oc_erp_product_template_merge SET erp_template_id = '" . (int)$erp_template_id . "', opencart_product_id = '2955', created_by = 'Manual Fix'";
        
        if ($mysqli->query($query)) {
            echo "✓ Mapping created successfully!\n";
            echo "OpenCart Product ID: 2955, ERP Template ID: $erp_template_id\n";
        } else {
            echo "✗ Failed to create mapping: " . $mysqli->error . "\n";
        }
    }
} else {
    echo "✗ Product 2955 does not exist in OpenCart\n";
}

// Verify the mapping was created
echo "\nVerification:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ Confirmed mapping: OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}, Created By: {$row['created_by']}\n";
    }
} else {
    echo "✗ No mapping found after creation attempt\n";
}

$mysqli->close();
echo "\n=== FIX COMPLETE ===\n";
?>