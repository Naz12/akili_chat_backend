<?php
// Debug script to test file upload issues
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== File Upload Debug ===\n";

// Test 1: Check PHP upload settings
echo "PHP Upload Settings:\n";
echo "  file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "\n";
echo "  upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "  post_max_size: " . ini_get('post_max_size') . "\n";
echo "  max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "  upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: 'System default') . "\n";

// Test 2: Check temp directory
$tempDir = sys_get_temp_dir();
echo "\nTemp Directory:\n";
echo "  Path: $tempDir\n";
echo "  Writable: " . (is_writable($tempDir) ? 'Yes' : 'No') . "\n";

// Test 3: Check storage directory
$storageDir = storage_path('app/public/attachments');
echo "\nStorage Directory:\n";
echo "  Path: $storageDir\n";
echo "  Exists: " . (is_dir($storageDir) ? 'Yes' : 'No') . "\n";
echo "  Writable: " . (is_writable($storageDir) ? 'Yes' : 'No') . "\n";

// Test 4: Try to create a test file directly
echo "\nDirect File Creation Test:\n";
$testContent = "Test content for upload debugging";
$testPath = $storageDir . '/debug_test.txt';
if (is_writable($storageDir)) {
    $result = file_put_contents($testPath, $testContent);
    if ($result !== false) {
        echo "  ✓ Successfully wrote test file\n";
        unlink($testPath); // Clean up
    } else {
        echo "  ✗ Failed to write test file\n";
    }
} else {
    echo "  ✗ Storage directory not writable\n";
}

// Test 5: Check for security extensions
echo "\nSecurity Extensions:\n";
$extensions = get_loaded_extensions();
$securityExtensions = array_filter($extensions, function($ext) {
    return stripos($ext, 'suhosin') !== false || 
           stripos($ext, 'snuffleupagus') !== false ||
           stripos($ext, 'security') !== false;
});

if (empty($securityExtensions)) {
    echo "  No known security extensions found\n";
} else {
    echo "  Found security extensions: " . implode(', ', $securityExtensions) . "\n";
}

echo "\n=== Debug Complete ===\n";