<?php
// Quick fix to assign product 2964 to store 0 and create ERP mapping table

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Product Fix Report</h2>";
    
    // 1. Fix store assignment for product 2964
    echo "<h3>1. Fixing Store Assignment</h3>";
    
    // Check if product is already assigned to a store
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_to_store WHERE product_id = ?");
    $stmt->execute([2964]);
    $store_count = $stmt->fetch()['count'];
    
    if ($store_count == 0) {
        // Assign to default store (0)
        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_to_store (product_id, store_id) VALUES (?, ?)");
        $stmt->execute([2964, 0]);
        echo "✅ Product 2964 assigned to store 0<br>";
    } else {
        echo "ℹ️ Product 2964 already assigned to " . $store_count . " store(s)<br>";
    }
    
    // 2. Create ERP mapping table if it doesn't exist
    echo "<h3>2. Creating ERP Mapping Table</h3>";
    
    // Check if table exists
    $stmt = $db->prepare("SHOW TABLES LIKE '" . DB_PREFIX . "wk_odoo_product'");
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        $create_table_sql = "
        CREATE TABLE `" . DB_PREFIX . "wk_odoo_product` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `oid` int(11) NOT NULL COMMENT 'Odoo Product ID',
            `opid` int(11) NOT NULL COMMENT 'OpenCart Product ID',
            `otid` int(11) DEFAULT NULL COMMENT 'Odoo Template ID',
            `name` varchar(255) DEFAULT NULL,
            `combination_id` int(11) DEFAULT 0,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_mapping` (`oid`, `opid`),
            KEY `idx_oid` (`oid`),
            KEY `idx_opid` (`opid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($create_table_sql);
        echo "✅ ERP mapping table created successfully<br>";
    } else {
        echo "ℹ️ ERP mapping table already exists<br>";
    }
    
    // 3. Create ERP mapping for product 2964
    echo "<h3>3. Creating ERP Mapping</h3>";
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "wk_odoo_product WHERE opid = ?");
    $stmt->execute([2964]);
    $mapping_count = $stmt->fetch()['count'];
    
    if ($mapping_count == 0) {
        // Create mapping - using template ID 1116 as the Odoo ID (from logs)
        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "wk_odoo_product (oid, opid, otid, name) VALUES (?, ?, ?, ?)");
        $stmt->execute([1116, 2964, 1116, 'TEEEEEEST']);
        echo "✅ ERP mapping created for product 2964 -> Odoo 1116<br>";
    } else {
        echo "ℹ️ ERP mapping already exists for product 2964<br>";
    }
    
    // 4. Verification
    echo "<h3>4. Final Verification</h3>";
    
    // Check store assignment
    $stmt = $db->prepare("SELECT store_id FROM " . DB_PREFIX . "product_to_store WHERE product_id = ?");
    $stmt->execute([2964]);
    $stores = $stmt->fetchAll();
    
    echo "<strong>Stores assigned:</strong> ";
    if ($stores) {
        foreach ($stores as $store) {
            echo $store['store_id'] . " ";
        }
        echo "✅<br>";
    } else {
        echo "❌ Still no stores assigned!<br>";
    }
    
    // Check ERP mapping
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "wk_odoo_product WHERE opid = ?");
    $stmt->execute([2964]);
    $mapping = $stmt->fetch();
    
    if ($mapping) {
        echo "<strong>ERP Mapping:</strong> Odoo " . $mapping['oid'] . " -> OpenCart " . $mapping['opid'] . " ✅<br>";
    } else {
        echo "<strong>ERP Mapping:</strong> ❌ Still not found!<br>";
    }
    
    echo "<br><div style='background: #e8f5e8; padding: 10px; border: 1px solid green;'>";
    echo "<strong>✅ Product 2964 should now be visible in OpenCart!</strong><br>";
    echo "You can search for it at: <br>";
    echo "• Product list: <a href='https://snusflix.com/admin/index.php?route=catalog/product&filter_name=TEEEEEEST'>Search TEEEEEEST</a><br>";
    echo "• ERP mappings: <a href='https://snusflix.com/admin/index.php?route=catalog/wk_odoo_product&filter_opid=2964'>OpenCart ID 2964</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>