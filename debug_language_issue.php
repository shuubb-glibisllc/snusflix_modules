<?php
// Debug language ID issue in product mapping display

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Language ID Issue Debug</h2>";
    
    // 1. Check current language setting
    echo "<h3>1. Current Language Setting</h3>";
    $stmt = $db->prepare("SELECT value FROM " . DB_PREFIX . "setting WHERE `key` = 'config_language_id' AND store_id = 0");
    $stmt->execute();
    $language_setting = $stmt->fetch();
    $current_language_id = $language_setting ? $language_setting['value'] : 1;
    echo "Current language ID: " . $current_language_id . "<br><br>";
    
    // 2. Test Product 2968 with different queries
    echo "<h3>2. Product 2968 Query Tests</h3>";
    
    $test_product_id = 2968;
    
    // Original admin query (problematic)
    echo "<strong>Admin Query (with language filter):</strong><br>";
    $stmt = $db->prepare("
        SELECT erp.*, pd.name 
        FROM " . DB_PREFIX . "erp_product_template_merge erp 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (erp.opencart_product_id = pd.product_id) 
        WHERE language_id = ? AND opencart_product_id = ?
    ");
    $stmt->execute([$current_language_id, $test_product_id]);
    $admin_result = $stmt->fetch();
    
    if ($admin_result) {
        echo "✅ Found with admin query<br>";
        echo "Name: " . ($admin_result['name'] ?? 'NULL') . "<br>";
    } else {
        echo "❌ NOT found with admin query<br>";
    }
    echo "<br>";
    
    // Fixed query (move language filter to WHERE clause)
    echo "<strong>Fixed Query (proper language handling):</strong><br>";
    $stmt = $db->prepare("
        SELECT erp.*, pd.name 
        FROM " . DB_PREFIX . "erp_product_template_merge erp 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (erp.opencart_product_id = pd.product_id AND pd.language_id = ?) 
        WHERE erp.opencart_product_id = ?
    ");
    $stmt->execute([$current_language_id, $test_product_id]);
    $fixed_result = $stmt->fetch();
    
    if ($fixed_result) {
        echo "✅ Found with fixed query<br>";
        echo "Name: " . ($fixed_result['name'] ?? 'NULL') . "<br>";
    } else {
        echo "❌ NOT found with fixed query<br>";
    }
    echo "<br>";
    
    // 3. Check what languages exist for Product 2968
    echo "<h3>3. Available Languages for Product 2968</h3>";
    $stmt = $db->prepare("SELECT language_id, name FROM " . DB_PREFIX . "product_description WHERE product_id = ?");
    $stmt->execute([$test_product_id]);
    $languages = $stmt->fetchAll();
    
    if ($languages) {
        echo "<strong>Product 2968 descriptions:</strong><br>";
        foreach ($languages as $lang) {
            $highlight = ($lang['language_id'] == $current_language_id) ? " style='background: lightgreen;'" : "";
            echo "<div" . $highlight . ">Language ID " . $lang['language_id'] . ": " . $lang['name'] . "</div>";
        }
    } else {
        echo "❌ No product descriptions found for Product 2968<br>";
    }
    echo "<br>";
    
    // 4. Test the filter with correct language
    echo "<h3>4. Find Correct Language for Product 2968</h3>";
    
    if ($languages) {
        foreach ($languages as $lang) {
            $stmt = $db->prepare("
                SELECT erp.*, pd.name 
                FROM " . DB_PREFIX . "erp_product_template_merge erp 
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (erp.opencart_product_id = pd.product_id) 
                WHERE language_id = ? AND opencart_product_id = ?
            ");
            $stmt->execute([$lang['language_id'], $test_product_id]);
            $lang_result = $stmt->fetch();
            
            echo "Language ID " . $lang['language_id'] . ": ";
            if ($lang_result) {
                $highlight = ($lang['language_id'] == $current_language_id) ? " (CURRENT LANGUAGE)" : "";
                echo "✅ Found" . $highlight . "<br>";
            } else {
                echo "❌ Not found<br>";
            }
        }
    }
    
    // 5. Show available languages in system
    echo "<h3>5. All Available Languages</h3>";
    $stmt = $db->prepare("SELECT language_id, name, code FROM " . DB_PREFIX . "language ORDER BY language_id");
    $stmt->execute();
    $all_languages = $stmt->fetchAll();
    
    foreach ($all_languages as $lang) {
        $current = ($lang['language_id'] == $current_language_id) ? " (CURRENT)" : "";
        echo "ID " . $lang['language_id'] . ": " . $lang['name'] . " (" . $lang['code'] . ")" . $current . "<br>";
    }
    
    // 6. Solution
    echo "<h3>6. 🎯 SOLUTION</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green; margin: 20px 0;'>";
    echo "<h4>Problem Identified:</h4>";
    echo "<p>The admin query filters by language_id BEFORE the LEFT JOIN, which excludes products that don't have descriptions in the current language.</p>";
    echo "<h4>Solutions:</h4>";
    echo "<ol>";
    echo "<li><strong>Quick Fix:</strong> Ensure Product 2968 has a description in language ID " . $current_language_id . "</li>";
    echo "<li><strong>Proper Fix:</strong> Move language_id filter to the JOIN condition in the model</li>";
    echo "</ol>";
    echo "</div>";
    
    // 7. Quick fix - add missing description
    echo "<h3>7. Quick Fix - Add Missing Description</h3>";
    
    // Check if description exists for current language
    $stmt = $db->prepare("SELECT product_id FROM " . DB_PREFIX . "product_description WHERE product_id = ? AND language_id = ?");
    $stmt->execute([$test_product_id, $current_language_id]);
    $desc_exists = $stmt->fetch();
    
    if (!$desc_exists) {
        // Get product name from any language
        $stmt = $db->prepare("SELECT name, description FROM " . DB_PREFIX . "product_description WHERE product_id = ? LIMIT 1");
        $stmt->execute([$test_product_id]);
        $any_desc = $stmt->fetch();
        
        if ($any_desc) {
            echo "Creating description for Product 2968 in language ID " . $current_language_id . "<br>";
            
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (?, ?, ?, ?, '', ?, '', '')");
            $stmt->execute([$test_product_id, $current_language_id, $any_desc['name'], $any_desc['description'], $any_desc['name']]);
            
            echo "✅ <strong>Description created!</strong> Product 2968 should now appear in admin UI<br>";
            
            // Test again
            $stmt = $db->prepare("
                SELECT erp.*, pd.name 
                FROM " . DB_PREFIX . "erp_product_template_merge erp 
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (erp.opencart_product_id = pd.product_id) 
                WHERE language_id = ? AND opencart_product_id = ?
            ");
            $stmt->execute([$current_language_id, $test_product_id]);
            $final_test = $stmt->fetch();
            
            if ($final_test) {
                echo "🎉 <strong>SUCCESS!</strong> Product 2968 now appears in admin query!<br>";
            }
        }
    } else {
        echo "Description already exists for current language<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>