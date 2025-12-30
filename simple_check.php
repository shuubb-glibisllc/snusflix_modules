<?php
$mysqli = new mysqli('localhost', 'snuszibe_snusflix', '5L?9GvirjwqL', 'snuszibe_snusflix');
echo "PRODUCT CHECK:\n";
$result = $mysqli->query("SELECT product_id, model, status FROM oc_product WHERE product_id = 2955");
echo ($result && $result->num_rows > 0) ? "Product 2955 EXISTS\n" : "Product 2955 NOT FOUND\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM oc_product WHERE DATE(date_added) = CURDATE()");
$row = $result->fetch_assoc();
echo "Products created today: " . $row['count'] . "\n";
?>