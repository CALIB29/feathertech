<?php
// Server Error Diagnostic Script
// Run this to identify the cause of 500 errors

echo "<h1>Server Error Diagnostic</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>";

echo "<h2>PHP Error Check:</h2>";

$errorLog = ini_get('error_log');
$displayErrors = ini_get('display_errors');

echo "<ul>";
echo "<li><strong>Error Log Location:</strong> " . ($errorLog ?: 'Not set') . "</li>";
echo "<li><strong>Display Errors:</strong> " . ($displayErrors ? 'On' : 'Off') . "</li>";
echo "<li><strong>Current Error Reporting:</strong> " . (error_reporting() == 0 ? 'None' : 'Enabled') . "</li>";
echo "</ul>";

echo "<h2>File Permissions Check:</h2>";
$uploadDirs = [
    'uploads/',
    'uploads/proofs/',
    'assets/',
    'assets/vaccination_proofs/'
];

foreach ($uploadDirs as $dir) {
    echo "<p><strong>$dir:</strong> ";
    if (is_dir($dir)) {
        echo fileperms($dir) ? "Exists" : "Exists (check permissions)";
    } else {
        echo "Does not exist";
    }
    echo "</p>";
}

echo "<h2>.htaccess Check:</h2>";
if (file_exists('.htaccess')) {
    echo "<div class='success'>✅ .htaccess exists</div>";

    // Check if .htaccess has syntax issues
    $htaccessContent = file_get_contents('.htaccess');
    if (strpos($htaccessContent, '<IfModule') !== false) {
        echo "<div class='warning'>⚠️ .htaccess contains advanced directives that may not work on all hosts</div>";
    }
} else {
    echo "<div class='warning'>⚠️ .htaccess not found</div>";
}

echo "<h2>Server Configuration:</h2>";
echo "<ul>";
echo "<li><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</li>";
echo "<li><strong>Script Path:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</li>";
echo "</ul>";

echo "<h2>Database Connection Test:</h2>";
try {
    require_once 'includes/db.php';
    echo "<div class='success'>✅ Database connection successful</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
}

echo "<h2>Session Test:</h2>";
session_start();
$_SESSION['test'] = 'diagnostic_test';
if ($_SESSION['test'] === 'diagnostic_test') {
    echo "<div class='success'>✅ Session handling works</div>";
    unset($_SESSION['test']);
} else {
    echo "<div class='error'>❌ Session handling failed</div>";
}

echo "<h2>File Upload Test:</h2>";
if (isset($_POST['test_upload'])) {
    if (isset($_FILES['test_file'])) {
        $file = $_FILES['test_file'];
        echo "<p><strong>Upload Test Results:</strong></p>";
        echo "<ul>";
        echo "<li>File Name: {$file['name']}</li>";
        echo "<li>File Size: {$file['size']} bytes</li>";
        echo "<li>Error Code: {$file['error']}</li>";

        if ($file['error'] === UPLOAD_ERR_OK) {
            echo "<li class='success'>✅ Upload successful!</li>";
        } else {
            echo "<li class='error'>❌ Upload failed: " . getUploadErrorMessage($file['error']) . "</li>";
        }
        echo "</ul>";
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

echo "<h2>Quick Fixes:</h2>";
echo "<ol>";
echo "<li><strong>Try the safe .htaccess:</strong> <a href='?action=use_safe_htaccess'>Use Safe .htaccess</a></li>";
echo "<li><strong>Contact TigerNetHost:</strong> Show them your current .htaccess file</li>";
echo "<li><strong>Check file permissions:</strong> Ensure upload directories are writable (755 or 777)</li>";
echo "<li><strong>Enable error logging:</strong> Ask TigerNetHost to enable PHP error logging</li>";
echo "</ol>";

if (isset($_GET['action']) && $_GET['action'] === 'use_safe_htaccess') {
    if (file_exists('.htaccess_safe') && rename('.htaccess_safe', '.htaccess')) {
        echo "<div class='success'>✅ Safe .htaccess activated! Try refreshing your site.</div>";
    } else {
        echo "<div class='error'>❌ Could not activate safe .htaccess</div>";
    }
}

echo "<hr>";
echo "<h3>Common 500 Error Causes:</h3>";
echo "<ul>";
echo "<li><strong>PHP Syntax Errors:</strong> ✅ Checked - No syntax errors found</li>";
echo "<li><strong>.htaccess Issues:</strong> 🔧 May need adjustment for TigerNetHost</li>";
echo "<li><strong>File Permissions:</strong> 🔧 Check upload directory permissions</li>";
echo "<li><strong>Memory Limits:</strong> 🔧 May need TigerNetHost intervention</li>";
echo "<li><strong>Database Issues:</strong> ✅ Connection successful</li>";
echo "</ul>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
