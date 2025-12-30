<?php
// Check user permissions for OpenCart admin

define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'snuszibe_snusflix');
define('DB_PASSWORD', '5L?9GvirjwqL');
define('DB_DATABASE', 'snuszibe_snusflix');
define('DB_PREFIX', 'oc_');

// You need to tell me your OpenCart admin user ID or username
// Check the URL when you're logged in - it usually shows user_token which we can use to find your user

try {
    $db = new PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>OpenCart Admin User Permissions Check</h2>";
    
    // 1. Show all admin users
    echo "<h3>1. Admin Users</h3>";
    $stmt = $db->prepare("SELECT user_id, username, firstname, lastname, status, user_group_id FROM " . DB_PREFIX . "user WHERE status = 1");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Name</th><th>Status</th><th>User Group</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['user_id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['firstname'] . " " . $user['lastname'] . "</td>";
        echo "<td>" . ($user['status'] ? 'Active' : 'Inactive') . "</td>";
        echo "<td>" . $user['user_group_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Show user groups and permissions
    echo "<h3>2. User Groups & Permissions</h3>";
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "user_group");
    $stmt->execute();
    $groups = $stmt->fetchAll();
    
    foreach ($groups as $group) {
        echo "<h4>Group: " . $group['name'] . " (ID: " . $group['user_group_id'] . ")</h4>";
        echo "<strong>Permissions:</strong> " . $group['permission'] . "<br><br>";
        
        // Decode permissions
        $permissions = json_decode($group['permission'], true);
        if ($permissions) {
            echo "<strong>Decoded Permissions:</strong><br>";
            echo "<pre>" . print_r($permissions, true) . "</pre>";
        }
        echo "<hr>";
    }
    
    // 3. Check category permissions specifically
    echo "<h3>3. Category Access Check</h3>";
    echo "Checking if any user groups have restrictions on categories 523 and 573...<br>";
    
    // Most OpenCart permissions are in JSON format in the permission field
    // We need to check if there are any category restrictions
    
    foreach ($groups as $group) {
        $permissions = json_decode($group['permission'], true);
        if ($permissions && isset($permissions['access'])) {
            echo "<strong>Group '" . $group['name'] . "' access permissions:</strong><br>";
            if (isset($permissions['access'])) {
                foreach ($permissions['access'] as $perm) {
                    echo "- " . $perm . "<br>";
                }
            }
        }
    }
    
    // 4. Check if there are specific product/category restrictions
    echo "<h3>4. Additional Permission Tables</h3>";
    
    // Check if there are other permission-related tables
    $stmt = $db->prepare("SHOW TABLES LIKE '" . DB_PREFIX . "%permission%'");
    $stmt->execute();
    $perm_tables = $stmt->fetchAll();
    
    if ($perm_tables) {
        foreach ($perm_tables as $table) {
            $table_name = $table[0];
            echo "<strong>Table: " . $table_name . "</strong><br>";
            
            $stmt = $db->prepare("SELECT * FROM " . $table_name . " LIMIT 5");
            $stmt->execute();
            $sample_data = $stmt->fetchAll();
            
            if ($sample_data) {
                echo "<table border='1' cellpadding='3'>";
                // Header
                echo "<tr>";
                foreach ($sample_data[0] as $key => $value) {
                    if (!is_numeric($key)) {
                        echo "<th>" . $key . "</th>";
                    }
                }
                echo "</tr>";
                
                // Data
                foreach ($sample_data as $row) {
                    echo "<tr>";
                    foreach ($row as $key => $value) {
                        if (!is_numeric($key)) {
                            echo "<td>" . $value . "</td>";
                        }
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "No data in this table.<br>";
            }
            echo "<br>";
        }
    } else {
        echo "No specific permission tables found.<br>";
    }
    
    // 5. Solution suggestions
    echo "<h3>5. Solutions</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7;'>";
    echo "<h4>🔧 If this is a permission issue, here are the solutions:</h4>";
    echo "<ol>";
    echo "<li><strong>Use Super Admin Account:</strong> Login with the main admin account that has full permissions</li>";
    echo "<li><strong>Check User Group:</strong> Make sure your user is in a group with 'catalog/product' and 'catalog/wk_odoo_product' permissions</li>";
    echo "<li><strong>Update Permissions:</strong> Go to System > Users > User Groups and give your group full catalog permissions</li>";
    echo "<li><strong>Category Access:</strong> Some systems restrict access to specific categories - check if categories 523/573 are restricted</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; margin-top: 10px;'>";
    echo "<h4>📝 To identify your current user:</h4>";
    echo "<p>Look at your browser URL when logged into OpenCart admin. Find the 'user_token' parameter. ";
    echo "Or check the username you're logged in with and find it in the table above.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo $e->getMessage();
    echo "</div>";
}
?>