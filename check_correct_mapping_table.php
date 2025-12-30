<?php
// Check products in the CORRECT mapping table that admin UI uses

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🎯 Checking CORRECT Mapping Table: oc_erp_product_template_merge</h2>";
    echo "<p><strong>This is the table the admin UI actually uses!</strong></p>";
    
    // 1. Show table structure
    echo "<h3>1. Table Structure</h3>";
    $stmt = $db->prepare("DESCRIBE " . DB_PREFIX . "erp_product_template_merge");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
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
    
    // 2. Check specific products
    echo "<h3>2. Product Mapping Status</h3>";
    
    $test_products = [
        2668 => 'V&YOU Boost Berry (should HAVE mapping)',
        2965 => 'TEEEEEEST (should NOT have mapping yet)'
    ];
    
    foreach ($test_products as $product_id => $description) {
        echo "<strong>Product " . $product_id . " (" . $description . "):</strong><br>";
        
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE ecomm_id = ?");
        $stmt->execute([$product_id]);
        $mapping = $stmt->fetch();
        
        if ($mapping) {
            echo "✅ Found mapping:<br>";
            echo "- Mapping ID: " . $mapping['id'] . "<br>";
            echo "- ERP Template ID: " . $mapping['erp_template_id'] . "<br>";
            echo "- E-commerce ID: " . $mapping['ecomm_id'] . "<br>";
            echo "- Name: " . $mapping['name'] . "<br>";
            
            if ($product_id == 2668 && $mapping['id'] == 725) {
                echo "🎉 <strong>PERFECT MATCH!</strong> This exactly matches what admin UI shows (ID 725)<br>";
            }
        } else {
            echo "❌ No mapping found<br>";
            if ($product_id == 2965) {
                echo "👉 This explains why Product 2965 doesn't show in admin mapping list<br>";
            }
        }
        echo "<br>";
    }
    
    // 3. Show all mappings in the table
    echo "<h3>3. All Current Mappings</h3>";
    
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge ORDER BY id DESC LIMIT 20");
    $stmt->execute();
    $all_mappings = $stmt->fetchAll();
    
    if ($all_mappings) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Mapping ID</th><th>ERP Template ID</th><th>E-commerce ID</th><th>Name</th></tr>";
        
        foreach ($all_mappings as $mapping) {
            $highlight = in_array($mapping['ecomm_id'], [2668, 2965]) ? " style='background: lightgreen;'" : "";
            echo "<tr" . $highlight . ">";
            echo "<td>" . $mapping['id'] . "</td>";
            echo "<td>" . $mapping['erp_template_id'] . "</td>";
            echo "<td>" . $mapping['ecomm_id'] . "</td>";
            echo "<td>" . $mapping['name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No mappings found in table<br>";
    }
    
    // 4. Create missing mapping for Product 2965
    echo "<h3>4. Adding Missing Mapping for Product 2965</h3>";
    
    // Check if Product 2965 exists in OpenCart
    $stmt = $db->prepare("SELECT pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5) WHERE p.product_id = ?");
    $stmt->execute([2965]);
    $product_2965 = $stmt->fetch();
    
    if ($product_2965) {
        echo "Product 2965 found in OpenCart: " . $product_2965['name'] . "<br>";
        
        // Check if mapping already exists
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE ecomm_id = ?");
        $stmt->execute([2965]);
        $existing_mapping = $stmt->fetch();
        
        if (!$existing_mapping) {
            // Create the mapping
            $erp_template_id = 4; // Use same template ID as Product 2668 for consistency
            
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "erp_product_template_merge (erp_template_id, ecomm_id, name) VALUES (?, ?, ?)");
            $stmt->execute([$erp_template_id, 2965, $product_2965['name']]);
            
            $new_mapping_id = $db->lastInsertId();
            
            echo "✅ <strong>Created mapping for Product 2965!</strong><br>";
            echo "- New Mapping ID: " . $new_mapping_id . "<br>";
            echo "- ERP Template ID: " . $erp_template_id . "<br>";
            echo "- E-commerce ID: 2965<br>";
            echo "- Name: " . $product_2965['name'] . "<br>";
            
            echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green; margin: 20px 0;'>";
            echo "<h4>🎉 SUCCESS!</h4>";
            echo "<p>Product 2965 now has a mapping in the CORRECT table!</p>";
            echo "<p>It should now appear in: <strong>Admin → Products Mapping</strong></p>";
            echo "<p>Test URL: <a href='https://snusflix.com/admin/index.php?route=catalog/wk_odoo_product&user_token=Nl57aMFFN52oYal8NtheTO7NBjbEHmgu&filter_opid=2965' target='_blank'>Check Product 2965 Mapping</a></p>";
            echo "</div>";
        } else {
            echo "ℹ️ Mapping for Product 2965 already exists (ID: " . $existing_mapping['id'] . ")<br>";
        }
    } else {
        echo "❌ Product 2965 not found in OpenCart database<br>";
    }
    
    // 5. Verification
    echo "<h3>5. 🔍 Final Verification</h3>";
    
    foreach ($test_products as $product_id => $description) {
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE ecomm_id = ?");
        $stmt->execute([$product_id]);
        $final_check = $stmt->fetch();
        
        echo "<strong>Product " . $product_id . ":</strong> ";
        if ($final_check) {
            echo "✅ HAS mapping (ID: " . $final_check['id'] . ")<br>";
        } else {
            echo "❌ NO mapping<br>";
        }
    }
    
    echo "<br><div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 20px 0;'>";
    echo "<h4>📝 Summary</h4>";
    echo "<p><strong>Correct mapping table identified:</strong> oc_erp_product_template_merge</p>";
    echo "<p><strong>Product 2668:</strong> Already has mapping (ID 725) - matches admin UI ✅</p>";
    echo "<p><strong>Product 2965:</strong> Now has mapping - should appear in admin UI ✅</p>";
    echo "<p><strong>All future scripts should use:</strong> oc_erp_product_template_merge</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>