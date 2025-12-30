<?php
// Identify the CORRECT mapping table that admin UI uses

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Finding the CORRECT Mapping Table</h2>";
    
    // 1. Find ALL potential mapping tables
    echo "<h3>1. All Potential Mapping Tables</h3>";
    
    $stmt = $db->prepare("SHOW TABLES LIKE '%mapping%'");
    $stmt->execute();
    $mapping_tables = $stmt->fetchAll();
    
    $stmt = $db->prepare("SHOW TABLES LIKE '%odoo%'");
    $stmt->execute();
    $odoo_tables = $stmt->fetchAll();
    
    echo "<strong>Tables with 'mapping':</strong><br>";
    foreach ($mapping_tables as $table) {
        echo "- " . $table[0] . "<br>";
    }
    
    echo "<br><strong>Tables with 'odoo':</strong><br>";
    foreach ($odoo_tables as $table) {
        echo "- " . $table[0] . "<br>";
    }
    
    // 2. Check each table for Product 2668 (which shows in admin)
    echo "<h3>2. Searching for Product 2668 (V&YOU Boost Berry)</h3>";
    echo "<p><strong>Admin UI shows:</strong> Product 2668 HAS mapping (ID 725, Odoo Template 4)</p>";
    
    $potential_tables = [
        DB_PREFIX . 'wk_odoo_product',
        DB_PREFIX . 'connector_template_mapping', 
        DB_PREFIX . 'connector_product_mapping',
        DB_PREFIX . 'product_mapping',
        DB_PREFIX . 'odoo_mapping',
        DB_PREFIX . 'wk_product_mapping',
        DB_PREFIX . 'template_mapping',
        DB_PREFIX . 'erp_mapping'
    ];
    
    foreach ($potential_tables as $table) {
        try {
            // Check if table exists
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if (!$stmt->fetch()) {
                echo "<strong>" . $table . ":</strong> Table does not exist<br>";
                continue;
            }
            
            // Get table structure
            $stmt = $db->prepare("DESCRIBE " . $table);
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "<strong>" . $table . ":</strong><br>";
            echo "Columns: ";
            $column_names = [];
            foreach ($columns as $col) {
                $column_names[] = $col['Field'];
            }
            echo implode(', ', $column_names) . "<br>";
            
            // Search for Product 2668 in various ways
            $search_patterns = [
                "SELECT COUNT(*) as count FROM " . $table . " WHERE opid = 2668",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE product_id = 2668",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE ecomm_id = 2668",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE opencart_id = 2668",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE oc_id = 2668"
            ];
            
            $found = false;
            foreach ($search_patterns as $pattern) {
                try {
                    $stmt = $db->prepare($pattern);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    if ($result['count'] > 0) {
                        echo "  ✅ Found " . $result['count'] . " entries for product 2668<br>";
                        
                        // Get the actual record
                        $select_pattern = str_replace("COUNT(*) as count", "*", $pattern);
                        $stmt = $db->prepare($select_pattern);
                        $stmt->execute();
                        $record = $stmt->fetch();
                        
                        echo "  📋 Record data: " . json_encode($record) . "<br>";
                        $found = true;
                        break;
                    }
                } catch (Exception $e) {
                    // Column doesn't exist, continue
                }
            }
            
            if (!$found) {
                echo "  ❌ No entries found for product 2668<br>";
            }
            
            echo "<br>";
            
        } catch (Exception $e) {
            echo "<strong>" . $table . ":</strong> Error - " . $e->getMessage() . "<br><br>";
        }
    }
    
    // 3. Check for product 2965 in the same tables
    echo "<h3>3. Searching for Product 2965 (TEEEEEEST)</h3>";
    echo "<p><strong>Admin UI shows:</strong> Product 2965 has NO mapping</p>";
    
    foreach ($potential_tables as $table) {
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if (!$stmt->fetch()) {
                continue;
            }
            
            echo "<strong>" . $table . ":</strong><br>";
            
            $search_patterns = [
                "SELECT COUNT(*) as count FROM " . $table . " WHERE opid = 2965",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE product_id = 2965",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE ecomm_id = 2965",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE opencart_id = 2965",
                "SELECT COUNT(*) as count FROM " . $table . " WHERE oc_id = 2965"
            ];
            
            $found = false;
            foreach ($search_patterns as $pattern) {
                try {
                    $stmt = $db->prepare($pattern);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    if ($result['count'] > 0) {
                        echo "  ✅ Found " . $result['count'] . " entries for product 2965<br>";
                        
                        // Get the actual record
                        $select_pattern = str_replace("COUNT(*) as count", "*", $pattern);
                        $stmt = $db->prepare($select_pattern);
                        $stmt->execute();
                        $record = $stmt->fetch();
                        
                        echo "  📋 Record data: " . json_encode($record) . "<br>";
                        $found = true;
                        break;
                    }
                } catch (Exception $e) {
                    // Column doesn't exist, continue
                }
            }
            
            if (!$found) {
                echo "  ❌ No entries found for product 2965<br>";
            }
            
            echo "<br>";
            
        } catch (Exception $e) {
            echo "<strong>" . $table . ":</strong> Error - " . $e->getMessage() . "<br><br>";
        }
    }
    
    // 4. Check actual admin controller files
    echo "<h3>4. 🎯 SOLUTION: Identify the WORKING table</h3>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; margin: 20px 0;'>";
    echo "<h4>Based on the evidence:</h4>";
    echo "<p><strong>Expected Results:</strong></p>";
    echo "<ul>";
    echo "<li>Product 2668 should be found in the CORRECT table (since admin shows it)</li>";
    echo "<li>Product 2965 should NOT be found in the CORRECT table (since admin doesn't show it)</li>";
    echo "</ul>";
    echo "<p><strong>The table that shows Product 2668 but NOT Product 2965 is the one the admin UI uses!</strong></p>";
    echo "</div>";
    
    // 5. Show table schemas for manual inspection
    echo "<h3>5. 📊 Table Schemas for Manual Review</h3>";
    
    foreach ($potential_tables as $table) {
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if (!$stmt->fetch()) {
                continue;
            }
            
            $stmt = $db->prepare("DESCRIBE " . $table);
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "<strong>" . $table . " structure:</strong><br>";
            echo "<table border='1' cellpadding='3'>";
            echo "<tr><th>Field</th><th>Type</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>" . $col['Field'] . "</td>";
                echo "<td>" . $col['Type'] . "</td>";
                echo "<td>" . $col['Key'] . "</td>";
                echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table><br>";
            
            // Show sample records
            $stmt = $db->prepare("SELECT * FROM " . $table . " LIMIT 3");
            $stmt->execute();
            $samples = $stmt->fetchAll();
            
            if ($samples) {
                echo "<strong>Sample records:</strong><br>";
                foreach ($samples as $sample) {
                    echo "- " . json_encode($sample) . "<br>";
                }
            }
            echo "<br>";
            
        } catch (Exception $e) {
            echo "<strong>" . $table . ":</strong> Error - " . $e->getMessage() . "<br><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>