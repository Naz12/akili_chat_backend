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
$testFile = $storageDir . '/direct_test.txt';
if (is_writable($storageDir)) {
    $result = file_put_contents($testFile, "Direct file creation test");
    echo "  Direct file creation: " . ($result ? "Success" : "Failed") . "\n";
    if ($result) {
        unlink($testFile);
    }
} else {
    echo "  Direct file creation: Skipped (directory not writable)\n";
}

// Test 5: Try to simulate file upload with a real file
echo "\nSimulated File Upload Test:\n";
$testFilePath = tempnam($tempDir, 'upload_test_');
file_put_contents($testFilePath, "This is a test file for upload simulation");

// Create an UploadedFile instance
$uploadedFile = new Illuminate\Http\UploadedFile(
    $testFilePath,
    'test_file.txt',
    'text/plain',
    filesize($testFilePath),
    UPLOAD_ERR_OK,
    true // test mode
);

echo "  Test file created: " . $uploadedFile->getClientOriginalName() . "\n";
echo "  Test file size: " . $uploadedFile->getSize() . "\n";
echo "  Test file valid: " . ($uploadedFile->isValid() ? 'Yes' : 'No') . "\n";

// Try to store it
echo "  Attempting to store file...\n";
$path = $uploadedFile->store('attachments', 'public');
echo "  Store result: " . ($path ? $path : 'false') . "\n";

// Clean up
unlink($testFilePath);
if ($path) {
    unlink(storage_path('app/public/' . $path));
}

echo "\n=== Debug Complete ===\n";