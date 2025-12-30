<?php
$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');
echo "Product 2955 mapping: ";
$result = $mysqli->query("SELECT * FROM oc_erp_product_template_merge WHERE opencart_product_id = 2955");
echo $result && $result->num_rows > 0 ? "FOUND" : "NOT FOUND";
echo "\nTotal mappings: ";
$count = $mysqli->query("SELECT COUNT(*) as c FROM oc_erp_product_template_merge")->fetch_assoc();
echo $count['c'];
?>