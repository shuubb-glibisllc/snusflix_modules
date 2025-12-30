<?php
// Diagnose custom modifications that might be blocking product visibility

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Custom Modification Diagnosis</h2>";
    
    // 1. Check for custom product visibility modifications
    echo "<h3>1. Checking for Custom Modifications</h3>";
    
    // Check modification table
    $stmt = $db->prepare("SHOW TABLES LIKE '" . DB_PREFIX . "modification'");
    $stmt->execute();
    $has_modification_table = $stmt->fetch();
    
    if ($has_modification_table) {
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "modification WHERE status = 1 AND code LIKE '%product%'");
        $stmt->execute();
        $modifications = $stmt->fetchAll();
        
        if ($modifications) {
            echo "<strong>Active product-related modifications found:</strong><br>";
            foreach ($modifications as $mod) {
                echo "- " . $mod['name'] . " (Code: " . $mod['code'] . ")<br>";
            }
        } else {
            echo "No product-related modifications found ✅<br>";
        }
    } else {
        echo "No modification table found ✅<br>";
    }
    
    // 2. Check for custom admin controller overrides
    echo "<h3>2. Checking for Custom Admin Controllers</h3>";
    
    $admin_product_files = [
        '/admin/controller/catalog/product.php',
        '/admin/controller/catalog/wk_odoo_product.php',
        '/admin/model/catalog/product.php'
    ];
    
    foreach ($admin_product_files as $file) {
        if (file_exists('..' . $file)) {
            $file_size = filesize('..' . $file);
            $mod_time = date("Y-m-d H:i:s", filemtime('..' . $file));
            echo "- " . $file . " exists (Size: " . $file_size . " bytes, Modified: " . $mod_time . ")<br>";
        } else {
            echo "- " . $file . " not found<br>";
        }
    }
    
    // 3. Check system settings that might affect visibility
    echo "<h3>3. System Settings Check</h3>";
    
    $settings_to_check = [
        'config_admin_limit',
        'config_limit_admin', 
        'config_product_limit',
        'config_cache',
        'config_compression'
    ];
    
    foreach ($settings_to_check as $setting_key) {
        $stmt = $db->prepare("SELECT value FROM " . DB_PREFIX . "setting WHERE `key` = ? AND store_id = 0");
        $stmt->execute([$setting_key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            echo "- " . $setting_key . ": " . $setting['value'] . "<br>";
        }
    }
    
    // 4. Direct raw query test
    echo "<h3>4. Raw Database Query Test</h3>";
    
    // Test the most basic query possible
    $stmt = $db->prepare("SELECT product_id, (SELECT name FROM " . DB_PREFIX . "product_description WHERE product_id = p.product_id AND language_id = 5 LIMIT 1) as name FROM " . DB_PREFIX . "product p WHERE product_id = 2964");
    $stmt->execute();
    $raw_result = $stmt->fetch();
    
    if ($raw_result) {
        echo "<strong>✅ Raw query successful:</strong><br>";
        echo "- Product ID: " . $raw_result['product_id'] . "<br>";
        echo "- Name: " . $raw_result['name'] . "<br>";
    } else {
        echo "❌ Raw query failed - product 2964 not found<br>";
    }
    
    // 5. Test what a working product looks like
    echo "<h3>5. Working Product Comparison</h3>";
    
    $stmt = $db->prepare("
        SELECT p.product_id, pd.name, p.status,
               (SELECT COUNT(*) FROM " . DB_PREFIX . "product_to_store WHERE product_id = p.product_id) as store_count,
               (SELECT COUNT(*) FROM " . DB_PREFIX . "product_to_category WHERE product_id = p.product_id) as category_count
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        WHERE p.status = 1 AND p.product_id != 2964
        ORDER BY p.product_id DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $working_products = $stmt->fetchAll();
    
    echo "<strong>Working products for comparison:</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Stores</th><th>Categories</th></tr>";
    foreach ($working_products as $wp) {
        echo "<tr>";
        echo "<td>" . $wp['product_id'] . "</td>";
        echo "<td>" . ($wp['name'] ?: 'No name') . "</td>";
        echo "<td>" . ($wp['status'] ? 'Enabled' : 'Disabled') . "</td>";
        echo "<td>" . $wp['store_count'] . "</td>";
        echo "<td>" . $wp['category_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. Emergency solution
    echo "<h3>6. 🚨 Emergency Solution</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7;'>";
    echo "<h4>Since standard fixes aren't working, try these:</h4>";
    echo "<ol>";
    echo "<li><strong>Create a NEW product manually</strong> in OpenCart admin to test if the issue affects all products</li>";
    echo "<li><strong>Check if there's a Redis/Memcached cache</strong> that needs clearing</li>";
    echo "<li><strong>Check OpenCart logs</strong> in system/storage/logs/ for errors</li>";
    echo "<li><strong>Temporarily disable all modifications</strong> in Extensions > Modifications</li>";
    echo "<li><strong>Check if there's a custom theme</strong> affecting the admin panel</li>";
    echo "</ol>";
    echo "</div>";
    
    // 7. Last resort - copy existing working product
    echo "<h3>7. 🔄 Last Resort - Clone Working Product</h3>";
    echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb;'>";
    echo "<p><strong>If nothing else works, we can:</strong></p>";
    echo "<p>1. Find a working product in the system</p>";
    echo "<p>2. Copy ALL its database entries</p>";
    echo "<p>3. Update them with the data from product 2964</p>";
    echo "<p>4. This would bypass any custom logic affecting product creation</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>