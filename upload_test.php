<?php
// Simple upload test script
echo "=== Simple Upload Test ===\n";

// Check if we received a file
if ($_FILES && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    echo "File received:\n";
    echo "  Name: " . $file['name'] . "\n";
    echo "  Size: " . $file['size'] . "\n";
    echo "  Type: " . $file['type'] . "\n";
    echo "  Temp name: " . $file['tmp_name'] . "\n";
    echo "  Error code: " . $file['error'] . "\n";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "✓ File uploaded successfully to temp location\n";
        // Try to move it
        $target = '/tmp/test_upload_' . uniqid() . '.txt';
        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo "✓ File moved successfully to: $target\n";
            // Clean up
            unlink($target);
        } else {
            echo "✗ Failed to move file\n";
        }
    } else {
        echo "✗ File upload failed with error code: " . $file['error'] . "\n";
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                echo "  - File exceeds upload_max_filesize directive\n";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "  - File exceeds MAX_FILE_SIZE directive\n";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "  - File was only partially uploaded\n";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "  - No file was uploaded\n";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "  - Missing temporary folder\n";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "  - Failed to write file to disk\n";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "  - File upload stopped by extension\n";
                break;
            default:
                echo "  - Unknown error\n";
                break;
        }
    }
} else {
    echo "No file received. Please upload a file.\n";
    ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file">
        <input type="submit" value="Upload">
    </form>
    <?php
}
?>