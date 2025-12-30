<?php
// Check the oc_erp_product_template_merge table for recent mappings
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== CHECKING OPENCART MAPPING TABLE ===\n\n";

// Check the structure of the mapping table first
echo "1. Table structure of oc_erp_product_template_merge:\n";
$result = $mysqli->query("DESCRIBE oc_erp_product_template_merge");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Column: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Default: {$row['Default']}\n";
    }
} else {
    echo "Table oc_erp_product_template_merge NOT found!\n";
}

// Check for recent mappings
echo "\n2. Recent mappings in oc_erp_product_template_merge:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge ORDER BY opencart_product_id DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "No mappings found in oc_erp_product_template_merge table!\n";
}

// Check for specific product 2955
echo "\n3. Check for specific product 2955:\n";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "FOUND: OpenCart ID: {$row['opencart_product_id']}, ERP Template ID: {$row['erp_template_id']}\n";
    }
} else {
    echo "Product 2955 mapping NOT found!\n";
}

// Check all mapping tables
echo "\n4. All ERP-related tables:\n";
$result = $mysqli->query("SHOW TABLES LIKE '%erp%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $table_name = $row[array_keys($row)[0]];
        echo "Table: $table_name\n";
        
        // Count records in each table
        $count_result = $mysqli->query("SELECT COUNT(*) as count FROM $table_name");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            echo "  Records: {$count_row['count']}\n";
        }
    }
} else {
    echo "No ERP tables found!\n";
}

$mysqli->close();
echo "\n=== CHECK COMPLETE ===\n";
?>