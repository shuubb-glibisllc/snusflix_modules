<?php
// Find the REAL mapping table that admin UI actually uses

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Finding the REAL Admin UI Mapping Table</h2>";
    echo "<p><strong>MYSTERY:</strong> Product 2668 shows in admin but NOT in oc_wk_odoo_product!</p>";
    
    // 1. Search ALL tables for Product 2668
    echo "<h3>1. Comprehensive Search for Product 2668</h3>";
    
    // Get ALL tables in database
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $all_tables = $stmt->fetchAll();
    
    $found_tables = [];
    
    foreach ($all_tables as $table) {
        $table_name = $table[0];
        
        // Skip system tables
        if (strpos($table_name, 'information_schema') !== false || 
            strpos($table_name, 'performance_schema') !== false ||
            strpos($table_name, 'mysql') !== false) {
            continue;
        }
        
        try {
            // Get table structure
            $stmt = $db->prepare("DESCRIBE " . $table_name);
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            $column_names = [];
            foreach ($columns as $col) {
                $column_names[] = $col['Field'];
            }
            
            // Look for columns that might contain product ID 2668
            $search_columns = [];
            foreach ($column_names as $col) {
                if (stripos($col, 'product') !== false || 
                    stripos($col, 'opid') !== false || 
                    stripos($col, 'id') !== false ||
                    stripos($col, 'opencart') !== false ||
                    stripos($col, 'ecomm') !== false) {
                    $search_columns[] = $col;
                }
            }
            
            // Search each potential column
            foreach ($search_columns as $col) {
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . $table_name . " WHERE " . $col . " = 2668");
                    $stmt->execute();
                    $result = $stmt->fetch();
                    
                    if ($result['count'] > 0) {
                        $found_tables[] = $table_name;
                        echo "<strong>✅ FOUND in " . $table_name . " (column: " . $col . ")</strong><br>";
                        
                        // Get the actual record
                        $stmt = $db->prepare("SELECT * FROM " . $table_name . " WHERE " . $col . " = 2668 LIMIT 3");
                        $stmt->execute();
                        $records = $stmt->fetchAll();
                        
                        foreach ($records as $record) {
                            echo "  📋 " . json_encode($record) . "<br>";
                        }
                        echo "<br>";
                        break; // Found in this table, move to next
                    }
                } catch (Exception $e) {
                    // Column might not be numeric, continue
                }
            }
        } catch (Exception $e) {
            // Table might not be accessible, continue
        }
    }
    
    if (empty($found_tables)) {
        echo "❌ Product 2668 not found in ANY table!<br>";
    }
    
    // 2. Check if admin uses a VIEW instead of table
    echo "<h3>2. Checking for Database VIEWs</h3>";
    
    $stmt = $db->prepare("
        SELECT TABLE_NAME, TABLE_TYPE 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'VIEW'
    ");
    $stmt->execute([DB_DATABASE]);
    $views = $stmt->fetchAll();
    
    if ($views) {
        echo "<strong>Database VIEWs found:</strong><br>";
        foreach ($views as $view) {
            echo "- " . $view['TABLE_NAME'] . "<br>";
            
            // Check if this view contains Product 2668
            try {
                $stmt = $db->prepare("SELECT * FROM " . $view['TABLE_NAME'] . " WHERE opid = 2668 OR product_id = 2668 OR id = 2668 LIMIT 1");
                $stmt->execute();
                $view_result = $stmt->fetch();
                
                if ($view_result) {
                    echo "  ✅ Product 2668 found in VIEW: " . json_encode($view_result) . "<br>";
                }
            } catch (Exception $e) {
                // View might not have those columns
            }
        }
    } else {
        echo "No database views found<br>";
    }
    
    // 3. Check bridge_skeleton tables (might be using different naming)
    echo "<h3>3. Bridge Skeleton Tables Search</h3>";
    
    $bridge_patterns = [
        DB_PREFIX . 'connector_%',
        DB_PREFIX . 'bridge_%', 
        DB_PREFIX . 'template_%',
        DB_PREFIX . 'erp_%'
    ];
    
    foreach ($bridge_patterns as $pattern) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$pattern]);
        $bridge_tables = $stmt->fetchAll();
        
        if ($bridge_tables) {
            echo "<strong>Tables matching " . $pattern . ":</strong><br>";
            foreach ($bridge_tables as $table) {
                $table_name = $table[0];
                echo "- " . $table_name . "<br>";
                
                // Check this table for Product 2668
                try {
                    $stmt = $db->prepare("DESCRIBE " . $table_name);
                    $stmt->execute();
                    $columns = $stmt->fetchAll();
                    
                    // Look for product-related columns
                    foreach ($columns as $col) {
                        $col_name = $col['Field'];
                        if (stripos($col_name, 'ecomm') !== false || 
                            stripos($col_name, 'opencart') !== false ||
                            stripos($col_name, 'oc_') !== false ||
                            $col_name === 'name') {
                            
                            try {
                                $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . $table_name . " WHERE " . $col_name . " = 2668");
                                $stmt->execute();
                                $result = $stmt->fetch();
                                
                                if ($result['count'] > 0) {
                                    echo "    ✅ Found in column " . $col_name . "<br>";
                                    
                                    $stmt = $db->prepare("SELECT * FROM " . $table_name . " WHERE " . $col_name . " = 2668 LIMIT 1");
                                    $stmt->execute();
                                    $record = $stmt->fetch();
                                    echo "    📋 " . json_encode($record) . "<br>";
                                }
                            } catch (Exception $e) {
                                // Continue if column doesn't work
                            }
                        }
                    }
                } catch (Exception $e) {
                    echo "    Error accessing " . $table_name . "<br>";
                }
            }
            echo "<br>";
        }
    }
    
    // 4. Search for "725" (the mapping ID shown in admin)
    echo "<h3>4. Searching for Mapping ID 725</h3>";
    echo "<p>Admin shows Product 2668 has mapping <strong>ID 725</strong></p>";
    
    foreach ($all_tables as $table) {
        $table_name = $table[0];
        
        if (strpos($table_name, 'information_schema') !== false || 
            strpos($table_name, 'performance_schema') !== false ||
            strpos($table_name, 'mysql') !== false) {
            continue;
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM " . $table_name . " WHERE id = 725 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                echo "<strong>✅ Found mapping ID 725 in " . $table_name . "</strong><br>";
                echo "📋 " . json_encode($result) . "<br><br>";
            }
        } catch (Exception $e) {
            // Table might not have 'id' column, continue
        }
    }
    
    // 5. Final summary
    echo "<h3>5. 🎯 SOLUTION SUMMARY</h3>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 20px 0;'>";
    echo "<h4>Investigation Results:</h4>";
    
    if (!empty($found_tables)) {
        echo "<p><strong>✅ Product 2668 found in these tables:</strong></p>";
        echo "<ul>";
        foreach ($found_tables as $table) {
            echo "<li>" . $table . "</li>";
        }
        echo "</ul>";
        echo "<p>One of these is likely the REAL mapping table the admin UI uses!</p>";
    } else {
        echo "<p><strong>❌ Product 2668 not found anywhere</strong></p>";
        echo "<p>This suggests:</p>";
        echo "<ul>";
        echo "<li>The admin might be using a different product ID internally</li>";
        echo "<li>The mapping might be stored in a non-standard way</li>";
        echo "<li>There might be a custom admin modification</li>";
        echo "</ul>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>