<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Log start of script
error_log("QR Generator Script Started");

// Include database connection
include 'db.php';

// Include QR code library
$qrLibPath = __DIR__ . '/../qr/lib/full/qrlib.php';
if (!file_exists($qrLibPath)) {
    $error = 'QR library not found at: ' . $qrLibPath;
    error_log($error);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => $error]));
}

error_log("Including QR library from: $qrLibPath");
require_once $qrLibPath;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type for AJAX responses
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($success, $data = [], $error = '') {
    $response = ['success' => $success];
    if ($success) {
        $response = array_merge($response, $data);
    } else {
        $response['error'] = $error;
    }
    echo json_encode($response);
    exit();
}

// Verify ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid or missing animal ID';
    error_log($error . ': ' . ($_GET['id'] ?? 'not set'));
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, [], $error);
    } else {
        header("Location: ../dashboard.php?error=invalid_id");
        exit();
    }
}

// Log request details
error_log("Generating QR code for animal ID: " . $_GET['id']);
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Is AJAX: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? 'Yes' : 'No'));

$animalId = (int)$_GET['id'];

// Configure QR code directory - using absolute path for reliability
$qrDir = 'assets/qrcodes/';
$absoluteQrDir = __DIR__ . '/../' . $qrDir;

// Create directory if it doesn't exist
if (!file_exists($absoluteQrDir)) {
    if (!mkdir($absoluteQrDir, 0755, true)) {
        error_log("Failed to create directory: $absoluteQrDir");
        header("Location: ../view_animal.php?id=$animalId&error=directory_creation_failed");
        exit();
    }
}

// Generate filename without timestamp in the filename
$qrFilename = 'animal_' . $animalId . '.png';
$qrFile = $qrDir . $qrFilename;
$absoluteQrFile = $absoluteQrDir . $qrFilename;

// Generate QR code content
$qrContent = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . 
             $_SERVER['HTTP_HOST'] . "/view_animal.php?id=$animalId";

try {
    // Create directory if it doesn't exist with proper permissions
    if (!is_dir($absoluteQrDir)) {
        error_log("Creating directory: $absoluteQrDir");
        if (!mkdir($absoluteQrDir, 0755, true)) {
            throw new Exception("Failed to create directory: $absoluteQrDir");
        }
    } else {
        error_log("Directory exists: $absoluteQrDir");
    }

    // Check if directory is writable
    if (!is_writable($absoluteQrDir)) {
        throw new Exception("Directory is not writable: $absoluteQrDir");
    }

    // Remove old QR code if exists
    if (file_exists($absoluteQrFile)) {
        error_log("Removing existing QR code: $absoluteQrFile");
        if (!unlink($absoluteQrFile)) {
            throw new Exception("Failed to remove existing QR code: $absoluteQrFile");
        }
    }

    // Generate the QR code with error handling
    if (!class_exists('QRcode')) {
        error_log('QRcode class not found');
        throw new Exception('QRcode class not found. Check if the QR library is properly included.');
    }
    
    // Ensure the directory is writable
    $dir = dirname($absoluteQrFile);
    if (!is_writable($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        error_log("Directory not writable: $dir (Permissions: $perms)");
        throw new Exception("Directory is not writable: $dir (Current permissions: $perms)");
    }
    
    // Generate the QR code
    error_log("Generating QR code to: $absoluteQrFile");
    error_log("QR Content: $qrContent");
    
    // Generate with error suppression but log any errors
    $oldErrorReporting = error_reporting(0);
    $error = '';
    
    // Set custom error handler to capture errors
    set_error_handler(function($errno, $errstr) use (&$error) {
        $error = $errstr;
        return true;
    });
    
    // Generate the QR code
    $result = QRcode::png($qrContent, $absoluteQrFile, QR_ECLEVEL_H, 10);
    
    // Restore error handling
    restore_error_handler();
    error_reporting($oldErrorReporting);
    
    if ($error) {
        error_log("QR Generation Error: $error");
        throw new Exception("QR code generation failed: $error");
    }
    
    if ($result === false) {
        throw new Exception('Failed to generate QR code. Check server error logs for details.');
    }
    
    // Verify the file was created
    if (!file_exists($absoluteQrFile)) {
        throw new Exception("QR code file was not created at $absoluteQrFile");
    }

    // Update database with new QR code path
    $currentTime = time();
    $stmt = $pdo->prepare("UPDATE animals SET qr_code = ?, last_modified = ? WHERE id = ?");
    $success = $stmt->execute([$qrFile, $currentTime, $animalId]);
    
    if (!$success) {
        throw new Exception("Failed to update database with QR code path");
    }
    
    // Verify the file was created and is readable
    if (!file_exists($absoluteQrFile) || !is_readable($absoluteQrFile)) {
        throw new Exception('Generated QR code file is not accessible: ' . $absoluteQrFile);
    }
    
    // Set proper permissions
    chmod($absoluteQrFile, 0644);
    
    // Return success response with full URL
    $qrUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
             $_SERVER['HTTP_HOST'] . 
             str_replace('//', '/', '/' . dirname($_SERVER['PHP_SELF']) . '/' . $qrFile) . 
             '?t=' . $currentTime;
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(true, [
            'qr_url' => $qrUrl,
            'timestamp' => $currentTime
        ]);
    } else {
        // Fallback for non-AJAX requests
        header("Location: ../view_animal.php?id=$animalId&qr_updated=1");
        exit();
    }
    
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $logMsg = "QR generation failed: " . $errorMsg . "\n";
    $logMsg .= "Backtrace: " . $e->getTraceAsString() . "\n";
    $logMsg .= "POST data: " . print_r($_POST, true) . "\n";
    $logMsg .= "GET data: " . print_r($_GET, true) . "\n";
    $logMsg .= "Server info: " . print_r([
        'PHP Version' => phpversion(),
        'GD Library' => function_exists('imagecreate') ? 'Installed' : 'Not Installed',
        'Free Disk Space' => disk_free_space('/'),
        'Memory Limit' => ini_get('memory_limit'),
        'Upload Max Filesize' => ini_get('upload_max_filesize'),
        'Post Max Size' => ini_get('post_max_size')
    ], true);
    
    error_log($logMsg);
    
    // Get any output that might have been generated
    $output = ob_get_clean();
    if (!empty($output)) {
        error_log("Unexpected output: " . $output);
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, [], $errorMsg . " (Check server logs for more details)");
    } else {
        header("Location: ../view_animal.php?id=$animalId&error=qr_generation_failed&message=" . urlencode($errorMsg));
        exit();
    }
}