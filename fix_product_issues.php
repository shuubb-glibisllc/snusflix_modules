<?php
// Fix all product issues found in diagnosis

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Fixing Product 2964 Issues</h2>";
    
    // 1. Get default language ID
    echo "<h3>1. Checking Default Language</h3>";
    $stmt = $db->prepare("SELECT language_id, code FROM " . DB_PREFIX . "language WHERE status = 1 ORDER BY sort_order LIMIT 1");
    $stmt->execute();
    $default_lang = $stmt->fetch();
    
    if ($default_lang) {
        echo "Default language: ID " . $default_lang['language_id'] . " (" . $default_lang['code'] . ")<br>";
        $default_language_id = $default_lang['language_id'];
    } else {
        $default_language_id = 1; // fallback
        echo "Using fallback language ID: 1<br>";
    }
    
    // 2. Fix duplicate store assignments
    echo "<h3>2. Fixing Store Assignments</h3>";
    // Delete all existing store assignments for product 2964
    $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = ?");
    $stmt->execute([2964]);
    
    // Add single store assignment
    $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_to_store (product_id, store_id) VALUES (?, ?)");
    $stmt->execute([2964, 0]);
    echo "✅ Fixed store assignment (single entry for store 0)<br>";
    
    // 3. Fix duplicate category assignments
    echo "<h3>3. Fixing Category Assignments</h3>";
    // Delete all existing category assignments for product 2964
    $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = ?");
    $stmt->execute([2964]);
    
    // Add single category assignments
    $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_to_category (product_id, category_id) VALUES (?, ?)");
    $stmt->execute([2964, 523]);
    $stmt->execute([2964, 573]);
    echo "✅ Fixed category assignments (single entries for 523, 573)<br>";
    
    // 4. Fix language/description issue
    echo "<h3>4. Fixing Language Description</h3>";
    
    // Check if description exists for default language
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_description WHERE product_id = ? AND language_id = ?");
    $stmt->execute([2964, $default_language_id]);
    $default_desc_exists = $stmt->fetch()['count'];
    
    if ($default_desc_exists == 0) {
        // Copy description from language 5 to default language
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "product_description WHERE product_id = ? AND language_id = 5");
        $stmt->execute([2964]);
        $desc = $stmt->fetch();
        
        if ($desc) {
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                2964, 
                $default_language_id, 
                $desc['name'], 
                $desc['description'] ?: '', 
                $desc['tag'] ?: '', 
                $desc['meta_title'] ?: $desc['name'], 
                $desc['meta_description'] ?: '', 
                $desc['meta_keyword'] ?: ''
            ]);
            echo "✅ Added description for default language ID " . $default_language_id . "<br>";
        }
    } else {
        echo "ℹ️ Description already exists for default language<br>";
    }
    
    // 5. Update product settings for better visibility
    echo "<h3>5. Optimizing Product Settings</h3>";
    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "product SET noindex = 0, quantity = 1, stock_status_id = 7 WHERE product_id = ?");
    $stmt->execute([2964]);
    echo "✅ Updated product: noindex=0, quantity=1, stock_status=In Stock<br>";
    
    // 6. Final verification
    echo "<h3>6. Final Verification</h3>";
    
    // Check stores
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_to_store WHERE product_id = ?");
    $stmt->execute([2964]);
    $store_count = $stmt->fetch()['count'];
    echo "Store assignments: " . $store_count . " " . ($store_count == 1 ? "✅" : "❌") . "<br>";
    
    // Check categories
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_to_category WHERE product_id = ?");
    $stmt->execute([2964]);
    $cat_count = $stmt->fetch()['count'];
    echo "Category assignments: " . $cat_count . " " . ($cat_count == 2 ? "✅" : "❌") . "<br>";
    
    // Check descriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_description WHERE product_id = ?");
    $stmt->execute([2964]);
    $desc_count = $stmt->fetch()['count'];
    echo "Language descriptions: " . $desc_count . " ✅<br>";
    
    // Test search
    $stmt = $db->prepare("
        SELECT p.product_id, pd.name, p.status, pts.store_id, GROUP_CONCAT(ptc.category_id) as categories
        FROM " . DB_PREFIX . "product p
        LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id AND pd.language_id = ?
        LEFT JOIN " . DB_PREFIX . "product_to_store pts ON p.product_id = pts.product_id  
        LEFT JOIN " . DB_PREFIX . "product_to_category ptc ON p.product_id = ptc.product_id
        WHERE p.product_id = ?
        GROUP BY p.product_id
    ");
    $stmt->execute([$default_language_id, 2964]);
    $test = $stmt->fetch();
    
    if ($test && $test['name'] && $test['store_id'] !== null && $test['categories']) {
        echo "<div style='background: #e8f5e8; padding: 10px; border: 1px solid green; margin-top: 20px;'>";
        echo "<h3>🎉 SUCCESS! Product 2964 should now be visible!</h3>";
        echo "<strong>Product Details:</strong><br>";
        echo "• ID: " . $test['product_id'] . "<br>";
        echo "• Name: " . $test['name'] . "<br>";
        echo "• Status: " . ($test['status'] ? 'Enabled' : 'Disabled') . "<br>";
        echo "• Store: " . $test['store_id'] . "<br>";
        echo "• Categories: " . $test['categories'] . "<br>";
        echo "<br><strong>Try searching again:</strong><br>";
        echo "• <a href='https://snusflix.com/admin/index.php?route=catalog/product&filter_name=TEEEEEEST'>Product Search</a><br>";
        echo "• <a href='https://snusflix.com/admin/index.php?route=catalog/wk_odoo_product&filter_opid=2964'>ERP Mapping Search</a>";
        echo "</div>";
    } else {
        echo "<div style='background: #ffebee; padding: 10px; border: 1px solid red;'>";
        echo "❌ Something is still wrong. Debug info:<br>";
        echo "Name: " . ($test['name'] ?: 'NULL') . "<br>";
        echo "Store: " . ($test['store_id'] !== null ? $test['store_id'] : 'NULL') . "<br>";
        echo "Categories: " . ($test['categories'] ?: 'NULL') . "<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>