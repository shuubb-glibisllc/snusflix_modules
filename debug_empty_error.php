<?php
// Debug empty error response from OpenCart API

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Debug Empty Error Response</h2>";
    echo "<p><strong>Product Template ID:</strong> 1121 (Odoo)</p>";
    echo "<p><strong>Product ID:</strong> 1124 (Odoo)</p>";
    
    // 1. Check recent API logs
    echo "<h3>1. Recent API Debug Logs</h3>";
    
    if (file_exists('sync_debug.log')) {
        $log_content = file_get_contents('sync_debug.log');
        $lines = explode("\n", $log_content);
        $recent_lines = array_slice($lines, -30); // Last 30 lines
        
        echo "<div style='background: #f5f5f5; padding: 10px; font-family: monospace; max-height: 400px; overflow-y: scroll; border: 1px solid #ddd;'>";
        foreach ($recent_lines as $line) {
            if (!empty(trim($line))) {
                $highlight = (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) ? " style='background: #ffebee; color: red;'" : "";
                echo "<div" . $highlight . ">" . htmlspecialchars($line) . "</div>";
            }
        }
        echo "</div><br>";
    } else {
        echo "No sync_debug.log found<br>";
    }
    
    // 2. Check if product exists in OpenCart
    echo "<h3>2. Check Product Existence in OpenCart</h3>";
    
    // Look for products that might correspond to Odoo template 1121
    $possible_products = array();
    
    // Check by model/SKU containing 1121
    $stmt = $db->prepare("SELECT product_id, model, sku FROM " . DB_PREFIX . "product WHERE model LIKE ? OR sku LIKE ? ORDER BY product_id DESC");
    $stmt->execute(['%1121%', '%1121%']);
    $products_by_ref = $stmt->fetchAll();
    
    if ($products_by_ref) {
        echo "<strong>Products with reference to 1121:</strong><br>";
        foreach ($products_by_ref as $prod) {
            echo "OpenCart ID: " . $prod['product_id'] . ", Model: " . $prod['model'] . ", SKU: " . $prod['sku'] . "<br>";
            $possible_products[] = $prod['product_id'];
        }
    }
    
    // Check ERP mapping for template 1121
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE erp_template_id = ?");
    $stmt->execute([1121]);
    $template_mapping = $stmt->fetch();
    
    if ($template_mapping) {
        echo "<strong>Found ERP template mapping:</strong><br>";
        echo "- Mapping ID: " . $template_mapping['id'] . "<br>";
        echo "- ERP Template ID: " . $template_mapping['erp_template_id'] . "<br>";
        echo "- OpenCart Product ID: " . $template_mapping['opencart_product_id'] . "<br>";
        echo "- Created By: " . $template_mapping['created_by'] . "<br>";
        
        $opencart_product_id = $template_mapping['opencart_product_id'];
        $possible_products[] = $opencart_product_id;
        
        // Check if this OpenCart product actually exists
        $stmt = $db->prepare("SELECT product_id, model, sku, status FROM " . DB_PREFIX . "product WHERE product_id = ?");
        $stmt->execute([$opencart_product_id]);
        $actual_product = $stmt->fetch();
        
        if ($actual_product) {
            echo "<strong>✅ OpenCart product exists:</strong><br>";
            echo "- Product ID: " . $actual_product['product_id'] . "<br>";
            echo "- Model: " . $actual_product['model'] . "<br>";
            echo "- SKU: " . $actual_product['sku'] . "<br>";
            echo "- Status: " . ($actual_product['status'] ? 'Enabled' : 'Disabled') . "<br>";
        } else {
            echo "<strong>❌ OpenCart product DOES NOT EXIST!</strong><br>";
            echo "This is likely the cause of the empty error.<br>";
        }
    } else {
        echo "<strong>❌ No ERP template mapping found for template 1121</strong><br>";
    }
    
    // 3. Test the update endpoint directly
    echo "<h3>3. 🧪 Test Update Endpoint</h3>";
    
    if (!empty($possible_products)) {
        $test_product_id = $possible_products[0];
        echo "<strong>Testing update on OpenCart product ID: " . $test_product_id . "</strong><br>";
        
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 20px 0;'>";
        echo "<h4>Possible Issues:</h4>";
        echo "<ul>";
        echo "<li><strong>Product not found:</strong> The OpenCart product doesn't exist</li>";
        echo "<li><strong>API validation error:</strong> Required fields missing in update data</li>";
        echo "<li><strong>Database constraint error:</strong> Foreign key or constraint violation</li>";
        echo "<li><strong>PHP error:</strong> Silent error in webservice model</li>";
        echo "</ul>";
        echo "</div>";
        
        // Check recent error logs
        echo "<strong>Recent PHP/Apache errors (if accessible):</strong><br>";
        $error_paths = ['error_log', '../error_log', '/var/log/apache2/error.log'];
        
        foreach ($error_paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $errors = file_get_contents($path);
                $recent_errors = array_slice(explode("\n", $errors), -10);
                
                foreach ($recent_errors as $error) {
                    if (stripos($error, date('Y-m-d')) !== false && !empty(trim($error))) {
                        echo "<div style='background: #ffebee; padding: 5px; font-size: 12px; color: red;'>" . htmlspecialchars($error) . "</div>";
                    }
                }
                break;
            }
        }
    } else {
        echo "<strong>No products found to test</strong><br>";
    }
    
    // 4. Recommendations
    echo "<h3>4. 🎯 Next Steps</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border: 2px solid green; margin: 20px 0;'>";
    echo "<h4>To Fix Empty Error:</h4>";
    echo "<ol>";
    echo "<li><strong>Check if product exists:</strong> Verify the OpenCart product ID exists</li>";
    echo "<li><strong>Review webservice logs:</strong> Check sync_debug.log for detailed error info</li>";
    echo "<li><strong>Test with simpler data:</strong> Try updating just name/price first</li>";
    echo "<li><strong>Check PHP errors:</strong> Look for silent PHP errors in error logs</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>