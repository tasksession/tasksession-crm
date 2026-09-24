<?php
// Diagnostic script for Task Session installation
header('Content-Type: text/plain');

echo "=== Task Session Installation Diagnostic Report ===\n\n";

// Check PHP Version
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Required: 8.0+\n";
echo "Status: " . (version_compare(PHP_VERSION, '8.0.0', '>=') ? "OK" : "FAILED") . "\n\n";

// Check Required Extensions
$required_extensions = [
    'mysqli' => 'MySQLi PHP Extension',
    'pdo' => 'PDO PHP Extension',
    'curl' => 'cURL PHP Extension',
    'openssl' => 'OpenSSL PHP Extension',
    'mbstring' => 'MBString PHP Extension',
    'iconv' => 'iconv PHP Extension',
    'imap' => 'IMAP PHP Extension',
    'gd' => 'GD PHP Extension',
    'zip' => 'Zip PHP Extension'
];

echo "=== Required Extensions ===\n";
foreach ($required_extensions as $ext => $name) {
    echo "$name: " . (extension_loaded($ext) ? "OK" : "MISSING") . "\n";
}
echo "\n";

// Check PHP Settings
echo "=== PHP Settings ===\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? "Enabled" : "Disabled") . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n\n";

// Check Directory Permissions
echo "=== Directory Permissions ===\n";
$directories = [
    '../includes',
    '../assets',
    '../assets/images',
    '../assets/uploads'
];

foreach ($directories as $dir) {
    echo "$dir: " . (is_writable($dir) ? "Writable" : "Not Writable") . "\n";
}
echo "\n";

// Check Database Connection
echo "=== Database Connection Test ===\n";
if (file_exists('../includes/config.php')) {
    echo "Config file found\n";
    include('../includes/config.php');
    
    // Check if constants are defined
    echo "Checking database constants:\n";
    echo "DB_SERVER defined: " . (defined('DB_SERVER') ? "Yes" : "No") . "\n";
    echo "DB_USER defined: " . (defined('DB_USER') ? "Yes" : "No") . "\n";
    echo "DB_PASS defined: " . (defined('DB_PASS') ? "Yes" : "No") . "\n";
    echo "DB_NAME defined: " . (defined('DB_NAME') ? "Yes" : "No") . "\n";
    
    if (defined('DB_SERVER') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        try {
            $test_conn = new mysqli(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
            if ($test_conn->connect_error) {
                echo "Database Connection: FAILED\n";
                echo "Error: " . $test_conn->connect_error . "\n";
            } else {
                echo "Database Connection: OK\n";
                $test_conn->close();
            }
        } catch (Exception $e) {
            echo "Database Connection: FAILED\n";
            echo "Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Database Connection: FAILED\n";
        echo "Error: One or more database constants are not defined\n";
    }
} else {
    echo "Database Connection: FAILED\n";
    echo "Error: Config file not found at ../includes/config.php\n";
}
?> 