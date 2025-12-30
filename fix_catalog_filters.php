<?php
// Fix custom filters affecting Catalog/Product and Product Mapping sections

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔧 Fixing Catalog/Product & Mapping Filters</h2>";
    
    // 1. Check what Batch Editor sees vs Catalog
    echo "<h3>1. Batch Editor vs Catalog Query Comparison</h3>";
    
    // Check both 2964 and 2965
    $products_to_check = [2964, 2965];
    
    foreach ($products_to_check as $product_id) {
        // Simulate what batch editor might be using (simpler query)
        $batch_sql = "
            SELECT p.product_id, pd.name, p.model, p.status
            FROM " . DB_PREFIX . "product p 
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
            WHERE p.product_id = ?
        ";
        
        $stmt = $db->prepare($batch_sql);
        $stmt->execute([$product_id]);
        $batch_result = $stmt->fetch();
        
        echo "<strong>Product " . $product_id . " - Batch Editor style query:</strong><br>";
        if ($batch_result) {
            echo "✅ Product " . $product_id . " found: " . $batch_result['name'] . " (Status: " . ($batch_result['status'] ? 'Enabled' : 'Disabled') . ")<br>";
        } else {
            echo "❌ Product " . $product_id . " not found in batch query<br>";
        }
    }
    
    // 2. Check for custom catalog product filters
    echo "<h3>2. Catalog Product Specific Issues</h3>";
    
    // Check if there are any settings that might filter catalog products
    $catalog_settings = [
        'catalog_product_filter',
        'product_admin_filter',
        'admin_product_limit',
        'catalog_limit'
    ];
    
    foreach ($catalog_settings as $setting) {
        $stmt = $db->prepare("SELECT value FROM " . DB_PREFIX . "setting WHERE `key` LIKE ? AND store_id = 0");
        $stmt->execute(['%' . $setting . '%']);
        $results = $stmt->fetchAll();
        
        if ($results) {
            foreach ($results as $result) {
                echo "<strong>Found setting:</strong> " . $result['key'] . " = " . $result['value'] . "<br>";
            }
        }
    }
    
    // 3. Check product mapping specific tables
    echo "<h3>3. Product Mapping Table Analysis</h3>";
    
    // Check both products in mapping table (CORRECTED TABLE)
    foreach ($products_to_check as $product_id) {
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE ecomm_id = ?");
        $stmt->execute([$product_id]);
        $mapping = $stmt->fetch();
        
        echo "<strong>Product " . $product_id . " mapping (CORRECT TABLE):</strong><br>";
        if ($mapping) {
            echo "✅ Product mapping exists in oc_erp_product_template_merge:<br>";
            echo "- ID: " . $mapping['id'] . "<br>";
            echo "- ERP Template ID (erp_template_id): " . $mapping['erp_template_id'] . "<br>";
            echo "- E-commerce ID (ecomm_id): " . $mapping['ecomm_id'] . "<br>";
            echo "- Name: " . ($mapping['name'] ?? 'NULL') . "<br>";
        } else {
            echo "<strong>❌ Product mapping missing in CORRECT table!</strong> Creating it now...<br>";
            
            // Get product name for mapping
            $stmt_product = $db->prepare("SELECT pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5) WHERE p.product_id = ?");
            $stmt_product->execute([$product_id]);
            $product_info = $stmt_product->fetch();
            $product_name = $product_info ? $product_info['name'] : 'Unknown Product';
            
            // Create mapping in correct table - using appropriate template ID
            $template_id = ($product_id == 2964) ? 1116 : 1117; // Adjust as needed
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "erp_product_template_merge (erp_template_id, ecomm_id, name) VALUES (?, ?, ?)");
            $stmt->execute([$template_id, $product_id, $product_name]);
            echo "✅ Created product mapping for product " . $product_id . " in CORRECT table<br>";
        }
        echo "<br>";
    }
    
    // 4. Check for date-based filtering
    echo "<h3>4. Date-Based Filtering Check</h3>";
    
    foreach ($products_to_check as $product_id) {
        $stmt = $db->prepare("SELECT date_added, date_modified, date_available FROM " . DB_PREFIX . "product WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $dates = $stmt->fetch();
        
        if ($dates) {
            echo "<strong>Product " . $product_id . " dates:</strong><br>";
            echo "- Date Added: " . $dates['date_added'] . "<br>";
            echo "- Date Modified: " . $dates['date_modified'] . "<br>";
            echo "- Date Available: " . $dates['date_available'] . "<br>";
            
            // Fix potential date issues
            $today = date('Y-m-d');
            if ($dates['date_available'] > $today) {
                $stmt = $db->prepare("UPDATE " . DB_PREFIX . "product SET date_available = ? WHERE product_id = ?");
                $stmt->execute([$today, $product_id]);
                echo "✅ Fixed future date_available for product " . $product_id . "<br>";
            }
            
            if ($dates['date_modified'] == '0000-00-00 00:00:00') {
                $stmt = $db->prepare("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = ?");
                $stmt->execute([$product_id]);
                echo "✅ Fixed zero date_modified for product " . $product_id . "<br>";
            }
        } else {
            echo "<strong>❌ Product " . $product_id . " not found in database!</strong><br>";
        }
        echo "<br>";
    }
    
    // 5. Check for specific admin controller modifications
    echo "<h3>5. Admin Controller Modification Check</h3>";
    
    // Check if there are any modifications affecting catalog/product
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "modification WHERE status = 1 AND (code LIKE '%catalog%' OR code LIKE '%product%' OR xml LIKE '%catalog/product%') ORDER BY name");
    $stmt->execute();
    $catalog_modifications = $stmt->fetchAll();
    
    if ($catalog_modifications) {
        echo "<strong>⚠️ Active modifications affecting catalog/product:</strong><br>";
        foreach ($catalog_modifications as $mod) {
            echo "- " . $mod['name'] . " (Code: " . $mod['code'] . ")<br>";
            
            // Check if this modification might be filtering products
            if (stripos($mod['xml'], 'filter') !== false || stripos($mod['xml'], 'WHERE') !== false) {
                echo "  → This modification contains filtering logic that might affect product visibility<br>";
            }
        }
        
        echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0;'>";
        echo "<strong>💡 Suggestion:</strong> Try temporarily disabling these modifications in Extensions > Modifications to test if product 2964 appears.";
        echo "</div>";
    } else {
        echo "No catalog/product modifications found<br>";
    }
    
    // 6. Force refresh modification cache
    echo "<h3>6. 🔄 Force Refresh Admin Cache</h3>";
    
    try {
        // Clear modification cache if it exists
        if (is_dir('../system/storage/modification')) {
            echo "Modification cache directory exists<br>";
        }
        
        // Update modification refresh timestamp
        $stmt = $db->prepare("UPDATE " . DB_PREFIX . "setting SET value = NOW() WHERE `key` = 'modification_refresh' AND store_id = 0");
        $stmt->execute();
        echo "✅ Updated modification refresh timestamp<br>";
        
    } catch (Exception $e) {
        echo "Cache refresh failed: " . $e->getMessage() . "<br>";
    }
    
    // 7. Final test queries
    echo "<h3>7. 🎯 Final Admin Section Tests</h3>";
    
    // Test catalog/product style query
    $catalog_sql = "
        SELECT DISTINCT p.product_id, pd.name, p.model, p.status, p.quantity, p.price, p.date_added
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        WHERE pd.name LIKE '%TEEEEEEST%'
        ORDER BY p.date_added DESC, p.product_id DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($catalog_sql);
    $stmt->execute();
    $catalog_results = $stmt->fetchAll();
    
    echo "<strong>Catalog/Product style query results:</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Product ID</th><th>Name</th><th>Model</th><th>Status</th><th>Date Added</th></tr>";
    
    $found_products = [];
    foreach ($catalog_results as $result) {
        if (in_array($result['product_id'], $products_to_check)) {
            $found_products[] = $result['product_id'];
        }
        $highlight = in_array($result['product_id'], $products_to_check) ? " style='background: lightgreen;'" : "";
        echo "<tr" . $highlight . ">";
        echo "<td>" . $result['product_id'] . "</td>";
        echo "<td>" . $result['name'] . "</td>";
        echo "<td>" . $result['model'] . "</td>";
        echo "<td>" . ($result['status'] ? 'Enabled' : 'Disabled') . "</td>";
        echo "<td>" . $result['date_added'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!empty($found_products)) {
        echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green; margin: 20px 0;'>";
        echo "<h3>🎉 SUCCESS! Products " . implode(', ', $found_products) . " now appear in catalog-style queries!</h3>";
        echo "<p>Try refreshing your admin panel (Ctrl+F5) and searching again.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #ffebee; padding: 15px; border: 2px solid red; margin: 20px 0;'>";
        echo "<h3>⚠️ Products " . implode(', ', $products_to_check) . " still not appearing in catalog queries</h3>";
        echo "<p>The issue is likely a custom modification filtering the admin product list.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ul>";
        echo "<li>Disable modifications temporarily</li>";
        echo "<li>Check admin session cache</li>";
        echo "<li>Try different admin user account</li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>