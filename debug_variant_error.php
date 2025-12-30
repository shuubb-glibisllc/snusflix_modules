<?php
// Debug the variant_data error during product updates from Odoo

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Debug: variant_data Error Analysis</h2>";
    
    // 1. Check recent API logs for variant_data errors
    echo "<h3>1. Recent API Logs</h3>";
    
    if (file_exists('sync_debug.log')) {
        $log_content = file_get_contents('sync_debug.log');
        $lines = explode("\n", $log_content);
        $recent_lines = array_slice($lines, -50); // Last 50 lines
        
        echo "<strong>Recent sync debug log entries:</strong><br>";
        echo "<div style='background: #f5f5f5; padding: 10px; font-family: monospace; max-height: 300px; overflow-y: scroll; border: 1px solid #ddd;'>";
        foreach ($recent_lines as $line) {
            if (stripos($line, 'variant') !== false || stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
                echo "<span style='background: yellow;'>" . htmlspecialchars($line) . "</span><br>";
            } else {
                echo htmlspecialchars($line) . "<br>";
            }
        }
        echo "</div><br>";
    } else {
        echo "No sync_debug.log file found<br>";
    }
    
    // 2. Check error logs
    echo "<h3>2. PHP Error Logs</h3>";
    $error_log_paths = [
        'error_log',
        '../error_log',
        'php_errors.log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log'
    ];
    
    foreach ($error_log_paths as $log_path) {
        if (file_exists($log_path) && is_readable($log_path)) {
            echo "<strong>Found error log:</strong> " . $log_path . "<br>";
            $error_content = file_get_contents($log_path);
            $error_lines = explode("\n", $error_content);
            $recent_errors = array_slice($error_lines, -20);
            
            $variant_errors = array_filter($recent_errors, function($line) {
                return stripos($line, 'variant_data') !== false;
            });
            
            if (!empty($variant_errors)) {
                echo "<div style='background: #ffebee; padding: 10px; font-family: monospace; max-height: 200px; overflow-y: scroll; border: 1px solid red;'>";
                foreach ($variant_errors as $error) {
                    echo "<span style='color: red;'>" . htmlspecialchars($error) . "</span><br>";
                }
                echo "</div><br>";
            }
        }
    }
    
    // 3. Check product update endpoint
    echo "<h3>3. Product Update Test</h3>";
    
    // Test with a simple product update to see current behavior
    echo "<p>Testing product update endpoint behavior...</p>";
    
    // Check if we can identify which products are being updated
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "erp_product_template_merge ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $recent_products = $stmt->fetchAll();
    
    echo "<strong>Recent product mappings (for testing):</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Mapping ID</th><th>ERP Template ID</th><th>OpenCart Product ID</th><th>Created By</th></tr>";
    foreach ($recent_products as $product) {
        echo "<tr>";
        echo "<td>" . $product['id'] . "</td>";
        echo "<td>" . $product['erp_template_id'] . "</td>";
        echo "<td>" . $product['opencart_product_id'] . "</td>";
        echo "<td>" . $product['created_by'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // 4. Check the webservice model for variant handling
    echo "<h3>4. Current Webservice Configuration</h3>";
    
    $webservice_file = 'admin/model/catalog/wk_webservices_tab.php';
    if (file_exists($webservice_file)) {
        echo "✅ Webservice model file exists<br>";
        
        // Check if our latest fix is in place
        $webservice_content = file_get_contents($webservice_file);
        if (stripos($webservice_content, 'PERMANENT LANGUAGE FIX') !== false) {
            echo "✅ Language fix is deployed<br>";
        }
        
        // Look for variant_data usage
        if (stripos($webservice_content, 'variant_data') !== false) {
            echo "⚠️ Found variant_data references in webservice<br>";
        } else {
            echo "ℹ️ No variant_data references in current webservice<br>";
        }
    }
    
    // 5. Provide solution
    echo "<h3>5. 🎯 Solution Analysis</h3>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 20px 0;'>";
    echo "<h4>Error Analysis:</h4>";
    echo "<p><strong>Error:</strong> \"cannot access local variable 'variant_data' where it is not associated with a value\"</p>";
    echo "<p><strong>Root Cause:</strong> This is a Python variable scope error happening in Odoo when trying to update products.</p>";
    echo "<p><strong>Location:</strong> The error is in the Odoo-side code, not the OpenCart side.</p>";
    echo "<br>";
    
    echo "<h4>Common Causes:</h4>";
    echo "<ul>";
    echo "<li><strong>Variable declared inside conditional block</strong> - variant_data defined in if/try block but used outside</li>";
    echo "<li><strong>Exception handling issue</strong> - variant_data created in try block but accessed in except/finally</li>";
    echo "<li><strong>Loop scope problem</strong> - variant_data created inside loop but used after loop</li>";
    echo "</ul>";
    echo "<br>";
    
    echo "<h4>Solution:</h4>";
    echo "<p><strong>Need to fix the Odoo Python code</strong> by initializing variant_data before it's used:</p>";
    echo "<pre style='background: #f8f8f8; padding: 10px; border: 1px solid #ddd;'>";
    echo "# Initialize variant_data at the beginning of the function\n";
    echo "variant_data = {}\n";
    echo "# or\n";
    echo "variant_data = None\n";
    echo "# or\n";
    echo "variant_data = []  # depending on expected data type";
    echo "</pre>";
    echo "</div>";
    
    // 6. Next steps
    echo "<h3>6. 📋 Next Steps</h3>";
    echo "<ol>";
    echo "<li><strong>Locate the Odoo Python file</strong> containing the product update logic</li>";
    echo "<li><strong>Find the variant_data variable</strong> usage in that file</li>";
    echo "<li><strong>Initialize variant_data</strong> at the beginning of the function/method</li>";
    echo "<li><strong>Test the update</strong> again from Odoo</li>";
    echo "</ol>";
    
    echo "<p><strong>Note:</strong> This error is in the Odoo system, not OpenCart. The fix needs to be applied to your Odoo installation.</p>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>