
<?php

// Always start session for POST/JSON API if you need user context (optional, but safe)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db.php';
require_once __DIR__ . '/qr/lib/full/qrlib.php';

header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Debug: Log POST data if ID is invalid
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    error_log('update_animal.php: Invalid or missing ID. POST data: ' . print_r($_POST, true));
    echo json_encode([
        'success' => false,
        'message' => 'Invalid ID',
        'post_data' => $_POST
    ]);
    exit();
}

$id = (int)$_POST['id'];
$action = $_POST['action'] ?? '';

try {
    // Fetch current animal data
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
    $stmt->execute([$id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) {
        throw new Exception("Animal not found");
    }

    $response = ['success' => false, 'message' => 'No action performed'];

    // Handle different actions
    switch ($action) {
        case 'update_qr':
            // Configure QR code directory
            $qrDir = 'assets/qrcodes/';
            $absoluteQrDir = $_SERVER['DOCUMENT_ROOT'] . '/' . $qrDir;

            // Create directory if needed
            if (!file_exists($absoluteQrDir)) {
                mkdir($absoluteQrDir, 0755, true);
            }

            // Generate filename
            $qrFilename = 'animal_' . $id . '.png';
            $qrFile = $qrDir . $qrFilename;
            $absoluteQrFile = $absoluteQrDir . $qrFilename;

            // Generate QR content
            $qrContent = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . 
                         $_SERVER['HTTP_HOST'] . "/view_animal.php?id=$id";

            // Remove old file if exists
            if (file_exists($absoluteQrFile)) {
                unlink($absoluteQrFile);
            }

            // Generate new QR code
            QRcode::png($qrContent, $absoluteQrFile, QR_ECLEVEL_H, 10);

            if (!file_exists($absoluteQrFile)) {
                throw new Exception("Failed to generate QR code");
            }

            // Update database
            $currentTime = time();
            $updateStmt = $pdo->prepare("UPDATE animals SET qr_code = ?, last_modified = ? WHERE id = ?");
            $updateStmt->execute([$qrFile, $currentTime, $id]);

            $response = [
                'success' => true,
                'qr_code' => $qrFile,
                'last_modified' => $currentTime
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}