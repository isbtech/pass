<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'config/config.php';

echo "<h1>System Test</h1>";

// Test database connection
echo "<h2>Database Connection</h2>";
try {
    $connection = db_connect();
    echo "Database connection successful!<br>";
    
    // Test tables
    $tables = ['users', 'audio_files', 'access_codes', 'access_logs', 'streaming_sessions'];
    foreach ($tables as $table) {
        $result = db_query("SHOW TABLES LIKE '$table'");
        $exists = mysqli_num_rows($result) > 0;
        echo "Table '$table': " . ($exists ? "Exists" : "Does not exist") . "<br>";
    }
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}

// Test file paths
echo "<h2>File Paths</h2>";
echo "AUDIO_DIR: " . AUDIO_DIR . "<br>";
echo "AUDIO_DIR exists: " . (is_dir(AUDIO_DIR) ? "Yes" : "No") . "<br>";
echo "AUDIO_DIR is writable: " . (is_writable(AUDIO_DIR) ? "Yes" : "No") . "<br>";

// Test access code generation
echo "<h2>Access Code Generation</h2>";
echo "Generated code: " . generate_access_code() . "<br>";

// Test UUID generation
echo "<h2>UUID Generation</h2>";
echo "Generated UUID: " . generate_uuid() . "<br>";

// Display server information
echo "<h2>Server Information</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";