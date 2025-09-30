<?php
// Create this file in the same directory as process_payment.php
// Access it via browser to test logging

header('Content-Type: text/plain');

echo "=== DEBUG TEST ===\n\n";

// Test 1: Check current directory
$currentDir = __DIR__;
echo "Current directory: $currentDir\n";

// Test 2: Check if directory is writable
$isWritable = is_writable($currentDir);
echo "Directory writable: " . ($isWritable ? 'YES' : 'NO') . "\n";

// Test 3: Try to create a test log file
$testLogFile = $currentDir . '/test_log.txt';
$testContent = "Test log entry at " . date('Y-m-d H:i:s') . "\n";

if (file_put_contents($testLogFile, $testContent, FILE_APPEND)) {
    echo "Test log file created: $testLogFile\n";
    echo "Content written successfully\n";
    
    // Read it back
    if (file_exists($testLogFile)) {
        echo "\nTest log content:\n";
        echo file_get_contents($testLogFile);
    }
} else {
    echo "FAILED to create test log file\n";
    echo "Possible reasons:\n";
    echo "- Directory not writable\n";
    echo "- PHP safe_mode restrictions\n";
    echo "- SELinux or similar security restrictions\n";
}

// Test 4: Check PHP error log location
echo "\nPHP error_log setting: " . ini_get('error_log') . "\n";

// Test 5: Alternative log locations
$alternativeLocations = [
    sys_get_temp_dir() . '/payment_debug.log',
    '/tmp/payment_debug.log',
    '../payment_debug.log'
];

echo "\nAlternative log locations to try:\n";
foreach ($alternativeLocations as $location) {
    $testWrite = @file_put_contents($location, "test\n", FILE_APPEND);
    echo "- $location: " . ($testWrite ? 'WRITABLE' : 'NOT WRITABLE') . "\n";
}

echo "\n=== END TEST ===\n";
?>