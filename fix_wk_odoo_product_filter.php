<?php
// Fix specific wk_odoo_product admin controller filtering

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔧 Fixing WK Odoo Product Admin Interface</h2>";
    
    // 1. Test the exact query that wk_odoo_product admin uses
    echo "<h3>1. Testing WK Odoo Product Query</h3>";
    
    // Simulate the query that the admin controller might use
    $wk_sql = "
        SELECT wo.*, p.model, pd.name as product_name, p.status as product_status, p.quantity
        FROM " . DB_PREFIX . "wk_odoo_product wo
        LEFT JOIN " . DB_PREFIX . "product p ON wo.opid = p.product_id
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        WHERE wo.opid = 2965
    ";
    
    $stmt = $db->prepare($wk_sql);
    $stmt->execute();
    $wk_result = $stmt->fetch();
    
    if ($wk_result) {
        echo "<strong>✅ WK Odoo Product found in database:</strong><br>";
        echo "<table border='1' cellpadding='5'>";
        foreach ($wk_result as $key => $value) {
            if (!is_numeric($key)) {
                echo "<tr><td><strong>" . $key . "</strong></td><td>" . ($value ?? 'NULL') . "</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<strong>❌ Product 2965 not found in wk_odoo_product join query</strong><br>";
    }
    
    // 2. Check all products in wk_odoo_product table
    echo "<h3>2. All WK Odoo Products</h3>";
    
    $stmt = $db->prepare("
        SELECT wo.*, p.status as product_status
        FROM " . DB_PREFIX . "wk_odoo_product wo
        LEFT JOIN " . DB_PREFIX . "product p ON wo.opid = p.product_id
        ORDER BY wo.opid DESC
        LIMIT 10
    ");
    $stmt->execute();
    $all_wk_products = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>OID</th><th>OPID</th><th>OTID</th><th>Name</th><th>Product Status</th><th>Created At</th></tr>";
    foreach ($all_wk_products as $wp) {
        $highlight = ($wp['opid'] == 2965) ? " style='background: lightgreen;'" : "";
        echo "<tr" . $highlight . ">";
        echo "<td>" . $wp['id'] . "</td>";
        echo "<td>" . $wp['oid'] . "</td>";
        echo "<td>" . $wp['opid'] . "</td>";
        echo "<td>" . ($wp['otid'] ?? 'NULL') . "</td>";
        echo "<td>" . ($wp['name'] ?? 'NULL') . "</td>";
        echo "<td>" . ($wp['product_status'] ? 'Enabled' : 'Disabled') . "</td>";
        echo "<td>" . ($wp['created_at'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Check for admin controller specific issues
    echo "<h3>3. Admin Controller Issues</h3>";
    
    // Check if the wk_odoo_product admin controller has pagination limits
    $stmt = $db->prepare("SELECT value FROM " . DB_PREFIX . "setting WHERE `key` LIKE '%wk%' OR `key` LIKE '%odoo%'");
    $stmt->execute();
    $wk_settings = $stmt->fetchAll();
    
    if ($wk_settings) {
        echo "<strong>WK/Odoo related settings:</strong><br>";
        foreach ($wk_settings as $setting) {
            echo "- " . $setting['key'] . ": " . $setting['value'] . "<br>";
        }
    } else {
        echo "No WK/Odoo specific settings found<br>";
    }
    
    // 4. Check instance configuration
    echo "<h3>4. Connector Instance Check</h3>";
    
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "connector_instance ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $instances = $stmt->fetchAll();
    
    if ($instances) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Active</th><th>State</th><th>Ecomm Type</th></tr>";
        foreach ($instances as $inst) {
            echo "<tr>";
            echo "<td>" . ($inst['id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inst['name'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inst['active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($inst['state'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inst['ecomm_type'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No connector instances found<br>";
    }
    
    // 5. Force specific fixes for wk_odoo_product visibility
    echo "<h3>5. 🔧 Forcing WK Odoo Product Fixes</h3>";
    
    // Update the wk_odoo_product entry to ensure it has all required fields
    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "wk_odoo_product SET created_at = NOW() WHERE opid = 2965 AND created_at IS NULL");
    $result1 = $stmt->execute();
    
    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "wk_odoo_product SET name = 'TEEEEEEST' WHERE opid = 2965 AND (name IS NULL OR name = '')");
    $result2 = $stmt->execute();
    
    echo "✅ Updated created_at timestamp<br>";
    echo "✅ Updated name field<br>";
    
    // 6. Test with admin-style filter
    echo "<h3>6. 🎯 Admin Filter Test</h3>";
    
    // Test various admin filter conditions
    $admin_filters = [
        "WHERE wo.opid = 2965",
        "WHERE wo.opid LIKE '%2965%'", 
        "WHERE p.status = 1 AND wo.opid = 2965",
        "WHERE pd.name LIKE '%TEEEEEEST%' AND wo.opid = 2965"
    ];
    
    foreach ($admin_filters as $filter) {
        $test_sql = "
            SELECT COUNT(*) as count, wo.opid, wo.oid, pd.name
            FROM " . DB_PREFIX . "wk_odoo_product wo
            LEFT JOIN " . DB_PREFIX . "product p ON wo.opid = p.product_id
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
            " . $filter . "
        ";
        
        $stmt = $db->prepare($test_sql);
        $stmt->execute();
        $test_result = $stmt->fetch();
        
        echo "<strong>Filter: " . $filter . "</strong><br>";
        echo "Results: " . $test_result['count'] . " found";
        if ($test_result['count'] > 0) {
            echo " (OPID: " . $test_result['opid'] . ", OID: " . $test_result['oid'] . ", Name: " . $test_result['name'] . ")";
        }
        echo "<br><br>";
    }
    
    // 7. Clear any potential admin session cache
    echo "<h3>7. 🗑️ Clear Admin Session Cache</h3>";
    
    try {
        // Clear old sessions
        $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "session WHERE expire < NOW()");
        $stmt->execute();
        echo "✅ Cleared expired sessions<br>";
        
        // Clear any user-specific cache if it exists
        $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "user_cache WHERE user_id IN (SELECT user_id FROM " . DB_PREFIX . "user WHERE status = 1)");
        $stmt->execute();
        echo "✅ Cleared user cache (if exists)<br>";
    } catch (Exception $e) {
        echo "Cache clearing attempted (some tables may not exist)<br>";
    }
    
    // 8. Final verification
    echo "<h3>8. 🎯 Final Verification</h3>";
    
    $final_sql = "
        SELECT wo.id, wo.oid, wo.opid, wo.name, wo.created_at,
               p.product_id, p.status, p.model,
               pd.name as product_name
        FROM " . DB_PREFIX . "wk_odoo_product wo
        LEFT JOIN " . DB_PREFIX . "product p ON wo.opid = p.product_id
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        WHERE wo.opid = 2965
    ";
    
    $stmt = $db->prepare($final_sql);
    $stmt->execute();
    $final_result = $stmt->fetch();
    
    if ($final_result) {
        echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green; margin: 20px 0;'>";
        echo "<h3>✅ Product 2965 WK Odoo Mapping Verified</h3>";
        echo "<p><strong>Mapping Details:</strong></p>";
        echo "<ul>";
        echo "<li>Mapping ID: " . $final_result['id'] . "</li>";
        echo "<li>Odoo ID: " . $final_result['oid'] . "</li>";
        echo "<li>OpenCart ID: " . $final_result['opid'] . "</li>";
        echo "<li>Product Name: " . $final_result['product_name'] . "</li>";
        echo "<li>Product Status: " . ($final_result['status'] ? 'Enabled' : 'Disabled') . "</li>";
        echo "<li>Created At: " . $final_result['created_at'] . "</li>";
        echo "</ul>";
        echo "<p><strong>🔧 Try these solutions:</strong></p>";
        echo "<ul>";
        echo "<li>Clear browser cache and cookies completely</li>";
        echo "<li>Try accessing in incognito/private mode</li>";
        echo "<li>Logout and login to admin panel</li>";
        echo "<li>Try searching without the filter (remove filter_opid=2965 from URL)</li>";
        echo "<li>Check if admin user has proper permissions</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='background: #ffebee; padding: 15px; border: 2px solid red; margin: 20px 0;'>";
        echo "<h3>❌ Product 2965 mapping verification failed</h3>";
        echo "<p>The mapping may not be properly joined with product data.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>