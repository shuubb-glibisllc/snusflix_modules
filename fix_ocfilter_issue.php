<?php
// Fix OCFilter modification blocking product 2964

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔧 Fixing OCFilter Issues for Product 2964</h2>";
    
    // 1. Check OCFilter-related tables
    echo "<h3>1. Checking OCFilter Tables</h3>";
    
    // Get all tables that might be related to OCFilter
    $stmt = $db->prepare("SHOW TABLES LIKE '%filter%'");
    $stmt->execute();
    $filter_tables = $stmt->fetchAll();
    
    echo "<strong>Filter-related tables found:</strong><br>";
    foreach ($filter_tables as $table) {
        $table_name = $table[0];
        echo "- " . $table_name . "<br>";
        
        // Check if product 2964 has any entries in filter tables
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . $table_name . " WHERE product_id = 2964");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            if ($count > 0) {
                echo "  → Product 2964 found in " . $table_name . " (" . $count . " entries)<br>";
            }
        } catch (Exception $e) {
            // Table might not have product_id column
        }
    }
    
    // 2. Check product filter assignments
    echo "<h3>2. Product Filter Data</h3>";
    
    // Common OCFilter tables to check
    $ocfilter_tables = [
        DB_PREFIX . 'product_filter',
        DB_PREFIX . 'ocfilter_option',
        DB_PREFIX . 'ocfilter_option_value', 
        DB_PREFIX . 'product_to_ocfilter'
    ];
    
    foreach ($ocfilter_tables as $table) {
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->fetch()) {
                echo "<strong>Table: " . $table . "</strong><br>";
                
                // Check product 2964 entries
                $stmt = $db->prepare("SELECT * FROM " . $table . " WHERE product_id = 2964");
                $stmt->execute();
                $entries = $stmt->fetchAll();
                
                if ($entries) {
                    echo "Product 2964 entries found:<br>";
                    foreach ($entries as $entry) {
                        echo "- " . json_encode($entry) . "<br>";
                    }
                } else {
                    echo "No entries for product 2964<br>";
                    
                    // Check what working products have
                    $stmt = $db->prepare("SELECT * FROM " . $table . " WHERE product_id IN (2961, 2962, 2963) LIMIT 3");
                    $stmt->execute();
                    $working_entries = $stmt->fetchAll();
                    
                    if ($working_entries) {
                        echo "Working products have these entries:<br>";
                        foreach ($working_entries as $entry) {
                            echo "- " . json_encode($entry) . "<br>";
                        }
                        
                        // Copy entries from working product to 2964
                        echo "<strong>🔄 Copying filter data from product 2963 to 2964:</strong><br>";
                        $stmt = $db->prepare("SELECT * FROM " . $table . " WHERE product_id = 2963");
                        $stmt->execute();
                        $copy_entries = $stmt->fetchAll();
                        
                        foreach ($copy_entries as $entry) {
                            unset($entry[0]); // Remove numeric keys
                            $entry['product_id'] = 2964;
                            
                            $columns = array_keys($entry);
                            $placeholders = ':' . implode(', :', $columns);
                            
                            try {
                                $insert_sql = "INSERT INTO " . $table . " (" . implode(', ', $columns) . ") VALUES (" . $placeholders . ")";
                                $stmt = $db->prepare($insert_sql);
                                $stmt->execute($entry);
                                echo "  ✅ Copied entry to product 2964<br>";
                            } catch (Exception $e) {
                                echo "  ❌ Could not copy entry: " . $e->getMessage() . "<br>";
                            }
                        }
                    }
                }
                echo "<br>";
            }
        } catch (Exception $e) {
            // Table doesn't exist, skip
        }
    }
    
    // 3. Check attribute assignments 
    echo "<h3>3. Product Attribute Assignments</h3>";
    
    // Check product attributes (OCFilter often depends on these)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_attribute WHERE product_id = 2964");
    $stmt->execute();
    $attr_count = $stmt->fetch()['count'];
    
    echo "Product 2964 attributes: " . $attr_count . "<br>";
    
    if ($attr_count == 0) {
        echo "<strong>🔄 Copying attributes from product 2963:</strong><br>";
        
        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_attribute (product_id, attribute_id, language_id, text) SELECT 2964, attribute_id, language_id, text FROM " . DB_PREFIX . "product_attribute WHERE product_id = 2963");
        $stmt->execute();
        echo "✅ Copied attributes from product 2963<br>";
    }
    
    // 4. Check options (if product has variants)
    echo "<h3>4. Product Options</h3>";
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_option WHERE product_id = 2964");
    $stmt->execute();
    $option_count = $stmt->fetch()['count'];
    
    echo "Product 2964 options: " . $option_count . "<br>";
    
    if ($option_count == 0) {
        echo "<strong>🔄 Copying options from product 2963:</strong><br>";
        
        try {
            // Copy product options
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_option (product_id, option_id, value, required) SELECT 2964, option_id, value, required FROM " . DB_PREFIX . "product_option WHERE product_id = 2963");
            $stmt->execute();
            
            // Copy option values
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_option_value (product_option_id, product_id, option_id, option_value_id, quantity, subtract, price, price_prefix, points, points_prefix, weight, weight_prefix) SELECT (SELECT product_option_id FROM " . DB_PREFIX . "product_option WHERE product_id = 2964 AND option_id = pov.option_id LIMIT 1), 2964, option_id, option_value_id, quantity, subtract, price, price_prefix, points, points_prefix, weight, weight_prefix FROM " . DB_PREFIX . "product_option_value pov WHERE product_id = 2963");
            $stmt->execute();
            
            echo "✅ Copied options from product 2963<br>";
        } catch (Exception $e) {
            echo "⚠️ Option copying failed: " . $e->getMessage() . "<br>";
        }
    }
    
    // 5. Final verification
    echo "<h3>5. 🎯 Final Test</h3>";
    
    // Test the exact query that OpenCart admin search uses
    $stmt = $db->prepare("
        SELECT DISTINCT p.product_id, pd.name, p.model, p.status
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        LEFT JOIN " . DB_PREFIX . "product_to_store pts ON (p.product_id = pts.product_id)
        WHERE p.status = 1 AND pts.store_id = 0 AND pd.name LIKE '%TEEEEEEST%'
        ORDER BY p.product_id DESC
    ");
    $stmt->execute();
    $final_results = $stmt->fetchAll();
    
    echo "<strong>Final search test results:</strong><br>";
    if ($final_results) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Product ID</th><th>Name</th><th>Model</th><th>Status</th></tr>";
        foreach ($final_results as $result) {
            $highlight = ($result['product_id'] == 2964) ? " style='background: lightgreen;'" : "";
            echo "<tr" . $highlight . ">";
            echo "<td>" . $result['product_id'] . "</td>";
            echo "<td>" . $result['name'] . "</td>";
            echo "<td>" . $result['model'] . "</td>";
            echo "<td>" . ($result['status'] ? 'Enabled' : 'Disabled') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if 2964 is now in results
        $found_2964 = false;
        foreach ($final_results as $result) {
            if ($result['product_id'] == 2964) {
                $found_2964 = true;
                break;
            }
        }
        
        if ($found_2964) {
            echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green; margin: 20px 0;'>";
            echo "<h3>🎉 SUCCESS! Product 2964 should now be visible!</h3>";
            echo "<p>The OCFilter data has been synchronized and the product should appear in searches.</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border: 2px solid orange; margin: 20px 0;'>";
            echo "<h3>⚠️ Still not visible - Try disabling OCFilter</h3>";
            echo "<p>Go to Extensions > Modifications and disable 'OCFilter Modification' temporarily to test.</p>";
            echo "</div>";
        }
        
    } else {
        echo "❌ No products found in search<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>