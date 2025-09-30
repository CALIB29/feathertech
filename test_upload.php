<?php
// PHP Upload Configuration Test
// Run this to verify your upload settings are working

echo "<h1>PHP Upload Configuration Test</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>";

echo "<h2>Current PHP Configuration:</h2>";
echo "<table border='1' style='border-collapse:collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$uploadSettings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time')
];

foreach ($uploadSettings as $setting => $value) {
    echo "<tr>";
    echo "<td><strong>$setting</strong></td>";
    echo "<td>$value</td>";

    // Check if settings are reasonable
    if ($setting === 'upload_max_filesize') {
        $size = (int)str_replace(['M', 'K'], ['', '000'], $value);
        if ($size >= 10) {
            echo "<td class='success'>✅ Good</td>";
        } else {
            echo "<td class='warning'>⚠️ Low ($value)</td>";
        }
    } elseif ($setting === 'post_max_size') {
        $size = (int)str_replace(['M', 'K'], ['', '000'], $value);
        if ($size >= 50) {
            echo "<td class='success'>✅ Good</td>";
        } else {
            echo "<td class='warning'>⚠️ Low ($value)</td>";
        }
    } else {
        echo "<td class='success'>✅ OK</td>";
    }
    echo "</tr>";
}

echo "</table>";

echo "<h2>Test Upload Form:</h2>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "Select image to upload: <input type='file' name='test_image' accept='image/*'><br><br>";
echo "<input type='submit' value='Test Upload' name='test_submit'>";
echo "</form>";

if (isset($_POST['test_submit']) && isset($_FILES['test_image'])) {
    echo "<h3>Upload Test Results:</h3>";
    $file = $_FILES['test_image'];

    echo "File Name: " . $file['name'] . "<br>";
    echo "File Size: " . $file['size'] . " bytes<br>";
    echo "File Type: " . $file['type'] . "<br>";
    echo "Error Code: " . $file['error'] . "<br>";

    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<span class='success'>✅ Upload would succeed!</span>";
    } else {
        echo "<span class='error'>❌ Upload error: " . getUploadErrorMessage($file['error']) . "</span>";
    }
}

function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "File exceeds upload_max_filesize";
        case UPLOAD_ERR_FORM_SIZE:
            return "File exceeds MAX_FILE_SIZE";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary directory";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "File upload stopped by extension";
        default:
            return "Unknown error";
    }
}

echo "<br><br><small>Note: This test only checks configuration, not actual file processing.</small>";
?>
