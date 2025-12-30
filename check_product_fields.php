<?php
// Check OpenCart product fields and current values

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 OpenCart Product Fields Check</h2>";
    echo "<p><strong>Checking product ID 2971 (TEEEEEEST5)</strong></p>";
    
    // 1. Check table structure
    echo "<h3>1. Product Table Fields</h3>";
    $stmt = $db->prepare("DESCRIBE " . DB_PREFIX . "product");
    $stmt->execute();
    $fields = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Description</th></tr>";
    foreach ($fields as $field) {
        $description = "";
        switch($field['Field']) {
            case 'model': $description = "Model/Код товара"; break;
            case 'sku': $description = "SKU"; break;
            case 'ean': $description = "EAN"; break;
            case 'upc': $description = "UPC/Barcode"; break;
            case 'jan': $description = "JAN"; break;
            case 'isbn': $description = "ISBN"; break;
            case 'mpn': $description = "MPN"; break;
            case 'product_id': $description = "Product ID"; break;
        }
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $description . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // 2. Check current values for product 2971
    echo "<h3>2. Current Values for Product 2971</h3>";
    $stmt = $db->prepare("SELECT product_id, model, sku, ean, upc, jan, isbn, mpn FROM " . DB_PREFIX . "product WHERE product_id = 2971");
    $stmt->execute();
    $product = $stmt->fetch();
    
    if ($product) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($product as $field => $value) {
            if (!is_numeric($field)) { // Skip numeric keys
                $highlight = empty($value) ? "style='background: #ffebee;'" : "style='background: #e8f5e8;'";
                echo "<tr " . $highlight . ">";
                echo "<td>" . $field . "</td>";
                echo "<td>" . ($value ?: '<em>EMPTY</em>') . "</td>";
                echo "</tr>";
            }
        }
        echo "</table><br>";
    } else {
        echo "❌ Product 2971 not found<br>";
    }
    
    // 3. Check what other products have model values
    echo "<h3>3. Sample Products with Model Values</h3>";
    $stmt = $db->prepare("SELECT product_id, model, sku FROM " . DB_PREFIX . "product WHERE model != '' LIMIT 5");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    if ($products) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Product ID</th><th>Model (Код товара)</th><th>SKU</th></tr>";
        foreach ($products as $product) {
            echo "<tr>";
            echo "<td>" . $product['product_id'] . "</td>";
            echo "<td>" . $product['model'] . "</td>";
            echo "<td>" . $product['sku'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "No products found with model values<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>