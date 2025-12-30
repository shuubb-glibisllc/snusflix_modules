<?php
// Deep diagnosis of product 2668 visibility issues

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Deep Product Diagnosis - Product ID 2668</h2>";
    
    // 1. Product basic details
    echo "<h3>1. Basic Product Details</h3>";
    $stmt = $db->prepare("
        SELECT p.*, pd.name, pd.description 
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id 
        WHERE p.product_id = ?
    ");
    $stmt->execute([2668]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo "<table border='1' cellpadding='5'>";
        foreach ($product as $key => $value) {
            if (!is_numeric($key)) {
                echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "❌ Product not found!";
        exit;
    }
    
    // 2. Store assignments
    echo "<h3>2. Store Assignments</h3>";
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "product_to_store WHERE product_id = ?");
    $stmt->execute([2668]);
    $stores = $stmt->fetchAll();
    
    if ($stores) {
        foreach ($stores as $store) {
            echo "Store ID: " . $store['store_id'] . "<br>";
        }
    } else {
        echo "❌ No store assignments!<br>";
    }
    
    // 3. Category assignments
    echo "<h3>3. Category Assignments</h3>";
    $stmt = $db->prepare("
        SELECT ptc.*, cd.name as category_name, c.status as category_status
        FROM " . DB_PREFIX . "product_to_category ptc
        LEFT JOIN " . DB_PREFIX . "category c ON ptc.category_id = c.category_id
        LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
        WHERE ptc.product_id = ?
    ");
    $stmt->execute([2668]);
    $categories = $stmt->fetchAll();
    
    if ($categories) {
        foreach ($categories as $cat) {
            echo "Category ID: " . $cat['category_id'] . " - Name: " . $cat['category_name'] . " - Status: " . ($cat['category_status'] ? 'Enabled' : 'Disabled') . "<br>";
        }
    } else {
        echo "❌ No category assignments!<br>";
    }
    
    // 4. Check if wk_odoo_product table structure is correct
    echo "<h3>4. ERP Table Structure</h3>";
    $stmt = $db->prepare("DESCRIBE " . DB_PREFIX . "wk_odoo_product");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 5. Check ERP mapping data
    echo "<h3>5. ERP Mapping Data</h3>";
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "wk_odoo_product WHERE opid = ?");
    $stmt->execute([2668]);
    $mapping = $stmt->fetch();
    
    if ($mapping) {
        echo "<table border='1' cellpadding='5'>";
        foreach ($mapping as $key => $value) {
            if (!is_numeric($key)) {
                echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "❌ No ERP mapping found!<br>";
    }
    
    // 6. Check recent products to compare
    echo "<h3>6. Recent Products (for comparison)</h3>";
    $stmt = $db->prepare("
        SELECT p.product_id, pd.name, p.status, p.quantity, 
               GROUP_CONCAT(pts.store_id) as stores,
               GROUP_CONCAT(ptc.category_id) as categories
        FROM " . DB_PREFIX . "product p
        LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id
        LEFT JOIN " . DB_PREFIX . "product_to_store pts ON p.product_id = pts.product_id
        LEFT JOIN " . DB_PREFIX . "product_to_category ptc ON p.product_id = ptc.product_id
        WHERE p.product_id > 2960
        GROUP BY p.product_id
        ORDER BY p.product_id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Quantity</th><th>Stores</th><th>Categories</th></tr>";
    foreach ($recent as $prod) {
        echo "<tr>";
        echo "<td>" . $prod['product_id'] . "</td>";
        echo "<td>" . ($prod['name'] ?: 'No name') . "</td>";
        echo "<td>" . ($prod['status'] ? 'Enabled' : 'Disabled') . "</td>";
        echo "<td>" . $prod['quantity'] . "</td>";
        echo "<td>" . ($prod['stores'] ?: 'None') . "</td>";
        echo "<td>" . ($prod['categories'] ?: 'None') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 7. Test direct search query
    echo "<h3>7. Direct Search Query Test</h3>";
    $stmt = $db->prepare("
        SELECT p.product_id, pd.name 
        FROM " . DB_PREFIX . "product p
        LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id
        WHERE pd.name LIKE '%TEEEEEEST%'
    ");
    $stmt->execute();
    $search_results = $stmt->fetchAll();
    
    if ($search_results) {
        echo "Search results:<br>";
        foreach ($search_results as $result) {
            echo "ID: " . $result['product_id'] . " - Name: " . $result['name'] . "<br>";
        }
    } else {
        echo "❌ No products found with name containing 'TEEEEEEST'<br>";
    }
    
    // 8. Check if there's a language issue
    echo "<h3>8. Language Check</h3>";
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "product_description WHERE product_id = ?");
    $stmt->execute([2668]);
    $descriptions = $stmt->fetchAll();
    
    if ($descriptions) {
        foreach ($descriptions as $desc) {
            echo "Language ID: " . $desc['language_id'] . " - Name: " . $desc['name'] . "<br>";
        }
    } else {
        echo "❌ No product descriptions found!<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>