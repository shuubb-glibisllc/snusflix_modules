<?php
// Quick debug script to check product 2950 in OpenCart database
echo "Checking product 2950 in OpenCart database...\n\n";

// Check if product exists
echo "1. Product in oc_product:\n";
$result = `mysql -h localhost -u snuszibe_snusflix -p'5L?9GvirjwqL' snuszibe_snusflix -e "SELECT product_id, model, sku, status FROM oc_product WHERE product_id = 2950;"`;
echo $result . "\n";

// Check store assignment
echo "2. Store assignment in oc_product_to_store:\n";
$result = `mysql -h localhost -u snuszibe_snusflix -p'5L?9GvirjwqL' snuszibe_snusflix -e "SELECT * FROM oc_product_to_store WHERE product_id = 2950;"`;
echo $result . "\n";

// Check category assignment
echo "3. Category assignment in oc_product_to_category:\n";
$result = `mysql -h localhost -u snuszibe_snusflix -p'5L?9GvirjwqL' snuszibe_snusflix -e "SELECT * FROM oc_product_to_category WHERE product_id = 2950;"`;
echo $result . "\n";

// Check if category 889 exists
echo "4. Check if category 889 exists:\n";
$result = `mysql -h localhost -u snuszibe_snusflix -p'5L?9GvirjwqL' snuszibe_snusflix -e "SELECT category_id, status FROM oc_category WHERE category_id = 889;"`;
echo $result . "\n";

// Check product description
echo "5. Product description:\n";
$result = `mysql -h localhost -u snuszibe_snusflix -p'5L?9GvirjwqL' snuszibe_snusflix -e "SELECT * FROM oc_product_description WHERE product_id = 2950;"`;
echo $result . "\n";

// Check ERP mapping
echo "6. ERP template mapping:\n";
$result = `mysql -h localhost -u snuszibe_snusflix -p'5L?9GvirjwqL' snuszibe_snusflix -e "SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2950;"`;
echo $result . "\n";

echo "Debug check complete.\n";
?>