<?php
// Product verification script
// Upload this to OpenCart root and run via browser to check if product 2964 exists

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Product Verification Report</h2>";
    echo "<p>Checking for product ID 2964...</p>";
    
    // Check if product exists
    $stmt = $db->prepare("SELECT product_id, model, quantity, status FROM " . DB_PREFIX . "product WHERE product_id = ?");
    $stmt->execute([2964]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo "<div style='color: green;'>";
        echo "<h3>✅ Product EXISTS in database!</h3>";
        echo "Product ID: " . $product['product_id'] . "<br>";
        echo "Model: " . $product['model'] . "<br>";
        echo "Quantity: " . $product['quantity'] . "<br>";
        echo "Status: " . ($product['status'] ? 'Enabled' : 'Disabled') . "<br>";
        echo "</div>";
        
        // Get product description
        $stmt = $db->prepare("SELECT name, description FROM " . DB_PREFIX . "product_description WHERE product_id = ?");
        $stmt->execute([2964]);
        $desc = $stmt->fetch();
        
        if ($desc) {
            echo "<p><strong>Name:</strong> " . htmlspecialchars($desc['name']) . "</p>";
        }
        
        // Check categories
        $stmt = $db->prepare("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = ?");
        $stmt->execute([2964]);
        $categories = $stmt->fetchAll();
        
        echo "<p><strong>Categories:</strong> ";
        if ($categories) {
            foreach ($categories as $cat) {
                echo $cat['category_id'] . " ";
            }
        } else {
            echo "<span style='color: red;'>NO CATEGORIES ASSIGNED!</span>";
        }
        echo "</p>";
        
        // Check if product appears in store
        $stmt = $db->prepare("SELECT store_id FROM " . DB_PREFIX . "product_to_store WHERE product_id = ?");
        $stmt->execute([2964]);
        $stores = $stmt->fetchAll();
        
        echo "<p><strong>Stores:</strong> ";
        if ($stores) {
            foreach ($stores as $store) {
                echo $store['store_id'] . " ";
            }
        } else {
            echo "<span style='color: red;'>NO STORES ASSIGNED!</span>";
        }
        echo "</p>";
        
        // Check ERP mappings (CORRECT TABLE)
        echo "<h3>ERP Mappings (CORRECT TABLE):</h3>";
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE ecomm_id = ?");
        $stmt->execute([2964]);
        $mapping = $stmt->fetch();
        
        if ($mapping) {
            echo "<div style='color: green;'>";
            echo "✅ Product mapping exists in CORRECT table<br>";
            echo "Mapping ID: " . $mapping['id'] . "<br>";
            echo "ERP Template ID: " . $mapping['erp_template_id'] . "<br>";
            echo "E-commerce ID: " . $mapping['ecomm_id'] . "<br>";
            echo "Name: " . $mapping['name'] . "<br>";
            echo "</div>";
        } else {
            echo "<div style='color: red;'>❌ NO PRODUCT MAPPING FOUND IN CORRECT TABLE!</div>";
            
            // Also check old table for comparison
            $stmt_old = $db->prepare("SELECT * FROM " . DB_PREFIX . "wk_odoo_product WHERE opid = ?");
            $stmt_old->execute([2964]);
            $mapping_old = $stmt_old->fetch();
            
            if ($mapping_old) {
                echo "<div style='color: orange;'>⚠️ Found mapping in OLD table (oc_wk_odoo_product) but this is NOT used by admin UI</div>";
            }
        }
        
    } else {
        echo "<div style='color: red;'>";
        echo "<h3>❌ Product NOT FOUND in database!</h3>";
        echo "Product ID 2964 does not exist in " . DB_PREFIX . "product table";
        echo "</div>";
        
        // Check recent products
        echo "<h3>Recent Products (last 5):</h3>";
        $stmt = $db->prepare("SELECT product_id, model, date_added FROM " . DB_PREFIX . "product ORDER BY product_id DESC LIMIT 5");
        $stmt->execute();
        $recent = $stmt->fetchAll();
        
        foreach ($recent as $prod) {
            echo "ID: " . $prod['product_id'] . " - Model: " . $prod['model'] . " - Added: " . $prod['date_added'] . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Database Connection Error:</h3>";
    echo $e->getMessage();
    echo "<br><br>Please update the database credentials at the top of this script.";
    echo "</div>";
}
?>