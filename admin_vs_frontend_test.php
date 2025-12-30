<?php
// Compare frontend vs admin product visibility for 2964

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Frontend vs Admin Query Comparison</h2>";
    
    // 1. Test frontend-style query (what customers see)
    echo "<h3>1. Frontend Query (Customer View)</h3>";
    $frontend_sql = "
        SELECT DISTINCT p.product_id, pd.name, p.model, p.status, p.quantity, p.price
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        LEFT JOIN " . DB_PREFIX . "product_to_store pts ON (p.product_id = pts.product_id)
        LEFT JOIN " . DB_PREFIX . "product_to_category ptc ON (p.product_id = ptc.product_id)
        WHERE p.status = 1 AND pts.store_id = 0 AND pd.name LIKE '%TEEEEEEST%'
        AND p.date_available <= NOW()
        ORDER BY p.product_id DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($frontend_sql);
    $stmt->execute();
    $frontend_results = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Product ID</th><th>Name</th><th>Model</th><th>Status</th><th>Quantity</th><th>Price</th></tr>";
    foreach ($frontend_results as $result) {
        $highlight = ($result['product_id'] == 2964) ? " style='background: lightgreen;'" : "";
        echo "<tr" . $highlight . ">";
        echo "<td>" . $result['product_id'] . "</td>";
        echo "<td>" . $result['name'] . "</td>";
        echo "<td>" . $result['model'] . "</td>";
        echo "<td>" . ($result['status'] ? 'Enabled' : 'Disabled') . "</td>";
        echo "<td>" . $result['quantity'] . "</td>";
        echo "<td>" . $result['price'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Test admin-style query (what admin panel uses)
    echo "<h3>2. Admin Query (Admin Panel View)</h3>";
    
    // Check if there are any admin-specific filters
    $admin_sql = "
        SELECT p.product_id, pd.name, p.model, p.status, p.quantity, p.price, p.date_added, p.date_modified
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        WHERE pd.name LIKE '%TEEEEEEST%'
        ORDER BY p.product_id DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($admin_sql);
    $stmt->execute();
    $admin_results = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Product ID</th><th>Name</th><th>Model</th><th>Status</th><th>Quantity</th><th>Price</th><th>Date Added</th><th>Date Modified</th></tr>";
    foreach ($admin_results as $result) {
        $highlight = ($result['product_id'] == 2964) ? " style='background: lightgreen;'" : "";
        echo "<tr" . $highlight . ">";
        echo "<td>" . $result['product_id'] . "</td>";
        echo "<td>" . $result['name'] . "</td>";
        echo "<td>" . $result['model'] . "</td>";
        echo "<td>" . ($result['status'] ? 'Enabled' : 'Disabled') . "</td>";
        echo "<td>" . $result['quantity'] . "</td>";
        echo "<td>" . $result['price'] . "</td>";
        echo "<td>" . $result['date_added'] . "</td>";
        echo "<td>" . $result['date_modified'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Check admin pagination/limit settings
    echo "<h3>3. Admin Settings Check</h3>";
    
    $admin_settings = [
        'config_admin_limit' => 'Admin pagination limit',
        'config_limit_admin' => 'Admin list limit',
        'config_product_limit' => 'Product display limit'
    ];
    
    foreach ($admin_settings as $setting_key => $description) {
        $stmt = $db->prepare("SELECT value FROM " . DB_PREFIX . "setting WHERE `key` = ? AND store_id = 0");
        $stmt->execute([$setting_key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            echo "<strong>" . $description . ":</strong> " . $setting['value'] . "<br>";
        } else {
            echo "<strong>" . $description . ":</strong> Not set (using default)<br>";
        }
    }
    
    // 4. Check for admin cache or session issues
    echo "<h3>4. Admin Cache Check</h3>";
    
    // Check if there are any admin-specific cache tables
    $cache_tables = ['oc_session', 'oc_cache'];
    foreach ($cache_tables as $cache_table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$cache_table]);
        if ($stmt->fetch()) {
            echo "<strong>" . $cache_table . " exists</strong> - Admin might be using cached data<br>";
            
            if ($cache_table == 'oc_cache') {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . $cache_table);
                $stmt->execute();
                $cache_count = $stmt->fetch()['count'];
                echo "  → Cache entries: " . $cache_count . "<br>";
            }
        }
    }
    
    // 5. Check admin-specific product visibility rules
    echo "<h3>5. Admin-Specific Rules</h3>";
    
    // Check if there are any admin user restrictions
    $stmt = $db->prepare("
        SELECT p.product_id, pd.name,
               (SELECT COUNT(*) FROM " . DB_PREFIX . "product_to_store WHERE product_id = p.product_id) as store_count,
               (SELECT COUNT(*) FROM " . DB_PREFIX . "product_to_category WHERE product_id = p.product_id) as category_count
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        WHERE p.product_id = 2964
    ");
    $stmt->execute();
    $product_rules = $stmt->fetch();
    
    if ($product_rules) {
        echo "<strong>Product 2964 visibility rules:</strong><br>";
        echo "- Store assignments: " . $product_rules['store_count'] . "<br>";
        echo "- Category assignments: " . $product_rules['category_count'] . "<br>";
    }
    
    // 6. Solutions
    echo "<h3>6. 🔧 Possible Solutions</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7;'>";
    echo "<h4>Since product shows on frontend but not admin:</h4>";
    echo "<ol>";
    echo "<li><strong>Clear admin browser cache/cookies completely</strong></li>";
    echo "<li><strong>Try incognito/private browser window</strong> for admin</li>";
    echo "<li><strong>Check admin pagination</strong> - product might be on a different page</li>";
    echo "<li><strong>Increase admin display limit</strong> in System > Settings > Server</li>";
    echo "<li><strong>Check admin session cache</strong> - logout and login again</li>";
    echo "<li><strong>Try different search terms</strong> - search by model 'Ref Odoo 1116'</li>";
    echo "</ol>";
    echo "</div>";
    
    // 7. Quick admin cache clear
    echo "<h3>7. 🗑️ Clear Admin Cache</h3>";
    echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb;'>";
    
    // Try to clear any obvious cache
    try {
        $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "session WHERE expire < NOW()");
        $stmt->execute();
        echo "✅ Cleared expired sessions<br>";
    } catch (Exception $e) {
        echo "Session table not found or accessible<br>";
    }
    
    try {
        $stmt = $db->prepare("TRUNCATE TABLE " . DB_PREFIX . "cache");
        $stmt->execute();
        echo "✅ Cleared cache table<br>";
    } catch (Exception $e) {
        echo "Cache table not found or accessible<br>";
    }
    
    echo "<p><strong>Try accessing admin again after clearing cache</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>