<?php
header('Content-Type: application/json');
require_once 'db.php';

// Error handling setup
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

try {
    // Validate input
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception('Invalid animal ID');
    }

    // Get animal data
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
    $stmt->execute([$id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) {
        throw new Exception('Animal not found');
    }

    // Include QR library
    require_once '../qr/lib/full/qrlib.php';

    // Prepare QR data
    $qrData = json_encode([
        'id' => $animal['id'],
        'type' => $animal['type'],
        'breed' => $animal['breed'],
        'vaccination' => [
            'type' => $animal['vaccination_type'] ?? 'None',
            'date' => $animal['vaccination_date'] ?? 'N/A',
            'time' => $animal['vaccination_time'] ?? 'N/A'
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ]);

    // Configure paths
    $qrDir = '../assets/images/';
    $qrFileName = 'qr_' . $animal['id'] . '_' . md5($qrData) . '.png';
    $absolutePath = realpath($qrDir) . DIRECTORY_SEPARATOR . $qrFileName;

    // Ensure directory exists and is writable
    if (!is_dir($qrDir)) {
        if (!mkdir($qrDir, 0755, true)) {
            throw new Exception('Failed to create QR directory');
        }
    }

    if (!is_writable($qrDir)) {
        throw new Exception('QR directory is not writable');
    }

    // Generate QR code
    QRcode::png($qrData, $absolutePath, QR_ECLEVEL_L, 10, 2);

    if (!file_exists($absolutePath)) {
        throw new Exception('Failed to generate QR code file');
    }

    // Update database
    $relativePath = '/assets/images/' . $qrFileName;
    $stmt = $pdo->prepare("UPDATE animals SET qr_code = ? WHERE id = ?");
    $stmt->execute([$relativePath, $id]);

    echo json_encode([
        'success' => true,
        'qr_path' => $relativePath,
        'message' => 'QR code successfully updated'
    ]);

} catch (Exception $e) {
    error_log('QR Update Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>