<?php
// Direct test of OpenCart API controller without routing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>🧪 Direct API Controller Test</h3>";

// Test if our oob.php file is being loaded properly
if (file_exists('oob.php')) {
    echo "✅ oob.php exists<br>";
    
    // Read first few lines to verify it's our fixed version
    $content = file_get_contents('oob.php');
    if (strpos($content, 'COMPLETE FIXED OpenCart API Controller') !== false) {
        echo "✅ File contains our complete fix<br>";
    } else {
        echo "❌ File does not contain our fix<br>";
    }
    
    if (strpos($content, 'PRODUCT UPDATE MODE') !== false) {
        echo "✅ Update mode logic is present<br>";
    } else {
        echo "❌ Update mode logic is missing<br>";
    }
} else {
    echo "❌ oob.php does not exist<br>";
}

// Test if logs are being written
echo "<br><h4>Log Test:</h4>";
$test_log = date('Y-m-d H:i:s') . " [TEST] Testing log functionality\n";
$result = file_put_contents('sync_debug.log', $test_log, FILE_APPEND | LOCK_EX);
if ($result !== false) {
    echo "✅ Can write to sync_debug.log<br>";
} else {
    echo "❌ Cannot write to sync_debug.log<br>";
}

// Check if log file was created and is readable
if (file_exists('sync_debug.log')) {
    echo "✅ sync_debug.log exists<br>";
    $log_content = file_get_contents('sync_debug.log');
    if (strpos($log_content, '[TEST]') !== false) {
        echo "✅ Our test entry is in the log<br>";
    }
} else {
    echo "❌ sync_debug.log was not created<br>";
}

// Test direct API URL access
echo "<br><h4>URL Routing Test:</h4>";
$current_url = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
echo "Current script: " . $current_url . "<br>";

$api_url = "https://snusflix.com/index.php?route=api/oob/product";
echo "Expected API URL: " . $api_url . "<br>";

// Check if OpenCart constants are available
if (defined('DIR_SYSTEM')) {
    echo "✅ DIR_SYSTEM is defined: " . DIR_SYSTEM . "<br>";
} else {
    echo "❌ DIR_SYSTEM is not defined - not in OpenCart context<br>";
}

echo "<br><h4>💡 Diagnosis:</h4>";
echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;'>";
echo "If logs show that update requests reach the API but then nothing happens:<br>";
echo "1. The API controller file might not be in the correct location<br>";
echo "2. OpenCart might not be loading our controller properly<br>";
echo "3. There might be a PHP error preventing execution<br>";
echo "4. The request format might not match what our controller expects<br>";
echo "</div>";

?>