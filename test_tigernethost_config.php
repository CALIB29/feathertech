<?php
// TigerNetHost Configuration Test Script
// Run this to verify your hosted PHP configuration

echo "<h1>TigerNetHost PHP Configuration Test</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>";

echo "<h2>Current PHP Configuration (TigerNetHost):</h2>";
echo "<table border='1' style='border-collapse:collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$uploadSettings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled'
];

foreach ($uploadSettings as $setting => $value) {
    echo "<tr>";
    echo "<td><strong>$setting</strong></td>";
    echo "<td>$value</td>";

    // Check if settings are reasonable for video uploads
    if ($setting === 'upload_max_filesize') {
        $size = (int)str_replace(['M', 'K'], ['', '000'], $value);
        if ($size >= 50) {
            echo "<td class='success'>✅ Good for videos</td>";
        } elseif ($size >= 10) {
            echo "<td class='warning'>⚠️ OK for images, may limit videos</td>";
        } else {
            echo "<td class='error'>❌ Too small for videos</td>";
        }
    } elseif ($setting === 'post_max_size') {
        $size = (int)str_replace(['M', 'K'], ['', '000'], $value);
        if ($size >= 100) {
            echo "<td class='success'>✅ Good for large uploads</td>";
        } else {
            echo "<td class='warning'>⚠️ May limit large files</td>";
        }
    } elseif ($setting === 'file_uploads') {
        if ($value === 'Enabled') {
            echo "<td class='success'>✅ File uploads enabled</td>";
        } else {
            echo "<td class='error'>❌ File uploads disabled</td>";
        }
    } else {
        echo "<td class='success'>✅ OK</td>";
    }
    echo "</tr>";
}

echo "</table>";

echo "<h2>Server Information:</h2>";
echo "<ul>";
echo "<li><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</li>";
echo "<li><strong>Current Working Directory:</strong> " . getcwd() . "</li>";
echo "</ul>";

echo "<h2>Upload Test Form:</h2>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "Select file to test (image or video): <input type='file' name='test_file' accept='image/*,video/*'><br><br>";
echo "<input type='submit' value='Test Upload' name='test_submit'>";
echo "</form>";

if (isset($_POST['test_submit']) && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Results:</h3>";
    $file = $_FILES['test_file'];

    echo "<p><strong>File Name:</strong> " . $file['name'] . "</p>";
    echo "<p><strong>File Size:</strong> " . $file['size'] . " bytes (" . round($file['size']/1024/1024, 2) . " MB)</p>";
    echo "<p><strong>File Type:</strong> " . $file['type'] . "</p>";
    echo "<p><strong>Error Code:</strong> " . $file['error'] . "</p>";

    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<div class='success'>✅ Upload would succeed!</div>";
    } else {
        echo "<div class='error'>❌ Upload error: " . getUploadErrorMessage($file['error']) . "</div>";
    }
}

function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "File exceeds upload_max_filesize (" . ini_get('upload_max_filesize') . ")";
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
            return "Unknown error (code: $errorCode)";
    }
}

echo "<hr>";
echo "<h3>TigerNetHost-Specific Notes:</h3>";
echo "<ul>";
echo "<li><strong>.htaccess Configuration:</strong> The .htaccess file should override PHP settings</li>";
echo "<li><strong>Contact Support:</strong> If uploads still fail, contact TigerNetHost support</li>";
echo "<li><strong>Common Issues:</strong> Mod_security rules may block large uploads</li>";
echo "<li><strong>Alternative:</strong> TigerNetHost may provide a control panel to adjust PHP settings</li>";
echo "</ul>";

echo "<h3>If Configuration Issues Persist:</h3>";
echo "<ol>";
echo "<li>Contact <strong>TigerNetHost support</strong> and request increased PHP limits</li>";
echo "<li>Provide them with these required settings:";
echo "<ul>";
echo "<li>upload_max_filesize: 100M</li>";
echo "<li>post_max_size: 110M</li>";
echo "<li>memory_limit: 256M</li>";
echo "<li>max_execution_time: 300</li>";
echo "</ul>";
echo "</li>";
echo "<li>Ask them to check if Mod_security is blocking file uploads</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
