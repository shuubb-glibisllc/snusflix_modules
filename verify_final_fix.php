<?php
// Quick verification of the final fix

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>✅ Final Fix Verification - Product 2964</h2>";
    
    // Test the exact query that OpenCart admin uses
    $stmt = $db->prepare("
        SELECT DISTINCT p.product_id, pd.name, p.model, p.status, p.quantity,
               GROUP_CONCAT(DISTINCT pts.store_id) as stores,
               GROUP_CONCAT(DISTINCT ptc.category_id) as categories
        FROM " . DB_PREFIX . "product p 
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
        LEFT JOIN " . DB_PREFIX . "product_to_store pts ON p.product_id = pts.product_id
        LEFT JOIN " . DB_PREFIX . "product_to_category ptc ON p.product_id = ptc.product_id
        WHERE p.product_id = 2964
        GROUP BY p.product_id
    ");
    $stmt->execute();
    $product = $stmt->fetch();
    
    if ($product) {
        echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green;'>";
        echo "<h3>🎉 Product 2964 Status:</h3>";
        echo "<p><strong>✅ Product ID:</strong> " . $product['product_id'] . "</p>";
        echo "<p><strong>✅ Name:</strong> " . $product['name'] . "</p>";
        echo "<p><strong>✅ Model:</strong> " . $product['model'] . "</p>";
        echo "<p><strong>✅ Status:</strong> " . ($product['status'] ? 'Enabled' : 'Disabled') . "</p>";
        echo "<p><strong>✅ Quantity:</strong> " . $product['quantity'] . "</p>";
        echo "<p><strong>✅ Stores:</strong> " . $product['stores'] . "</p>";
        echo "<p><strong>✅ Categories:</strong> " . $product['categories'] . "</p>";
        echo "</div>";
        
        // Test search functionality
        echo "<h3>🔍 Search Test</h3>";
        $stmt = $db->prepare("
            SELECT p.product_id, pd.name 
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 5)
            LEFT JOIN " . DB_PREFIX . "product_to_store pts ON p.product_id = pts.product_id
            WHERE pd.name LIKE '%TEEEEEEST%' AND pts.store_id = 0 AND p.status = 1
            ORDER BY p.product_id DESC
        ");
        $stmt->execute();
        $search_results = $stmt->fetchAll();
        
        echo "<p><strong>Search results for 'TEEEEEEST':</strong></p>";
        if ($search_results) {
            echo "<ul>";
            foreach ($search_results as $result) {
                $highlight = ($result['product_id'] == 2964) ? " style='background: yellow; font-weight: bold;'" : "";
                echo "<li" . $highlight . ">Product ID: " . $result['product_id'] . " - Name: " . $result['name'] . "</li>";
            }
            echo "</ul>";
            
            // Check if 2964 is in results
            $found_2964 = false;
            foreach ($search_results as $result) {
                if ($result['product_id'] == 2964) {
                    $found_2964 = true;
                    break;
                }
            }
            
            if ($found_2964) {
                echo "<div style='background: #d1ecf1; padding: 10px; border: 1px solid green;'>";
                echo "<h3>🎉 SUCCESS! Product 2964 found in search results!</h3>";
                echo "</div>";
            } else {
                echo "<div style='background: #ffebee; padding: 10px; border: 1px solid orange;'>";
                echo "<h3>⚠️ Product 2964 not found in search results</h3>";
                echo "<p>This might be due to search indexing or custom search implementation.</p>";
                echo "</div>";
            }
        } else {
            echo "<p>No search results found for 'TEEEEEEST'</p>";
        }
        
    } else {
        echo "<div style='background: #ffebee; padding: 15px; border: 2px solid red;'>";
        echo "<h3>❌ Product 2964 still has issues</h3>";
        echo "</div>";
    }
    
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; margin-top: 20px;'>";
    echo "<h3>🎯 Next Steps:</h3>";
    echo "<p>1. <strong>Clear browser cache and cookies</strong></p>";
    echo "<p>2. <strong>Try searching again:</strong></p>";
    echo "<p>   • <a href='https://snusflix.com/admin/index.php?route=catalog/product&filter_name=TEEEEEEST' target='_blank'>Product Search</a></p>";
    echo "<p>   • <a href='https://snusflix.com/admin/index.php?route=catalog/wk_odoo_product&filter_opid=2964' target='_blank'>ERP Mapping</a></p>";
    echo "<p>3. <strong>If still not working:</strong> The issue might be a custom modification or caching system</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>