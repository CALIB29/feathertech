<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON content type for AJAX responses
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    // Verify animal ID is provided
    if (!isset($_POST['animal_id']) || !is_numeric($_POST['animal_id'])) {
        throw new Exception('Invalid animal ID');
    }
    
    $animalId = (int)$_POST['animal_id'];
    
    // Include database connection
    require_once 'db.php';
    
    // Get current image path from database
    $stmt = $pdo->prepare("SELECT image_path FROM animals WHERE id = ?");
    $stmt->execute([$animalId]);
    $animal = $stmt->fetch();
    
    if (!$animal) {
        throw new Exception('Animal not found');
    }
    
    $imagePath = $animal['image_path'];
    
    // If there's an image to delete
    if (!empty($imagePath)) {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/');
        
        // Delete the file if it exists
        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                throw new Exception('Failed to delete image file');
            }
        }
        
        // Update database to remove image path
        $updateStmt = $pdo->prepare("UPDATE animals SET image_path = NULL, last_modified = ? WHERE id = ?");
        $success = $updateStmt->execute([time(), $animalId]);
        
        if (!$success) {
            throw new Exception('Failed to update database');
        }
        
        // Log the action (optional)
        error_log("Deleted image for animal ID: $animalId");
        
        // Return success response
        sendJsonResponse(true, 'Image deleted successfully', [
            'redirect' => "view_animal.php?id=$animalId&deleted=1"
        ]);
    } else {
        throw new Exception('No image found to delete');
    }
    
} catch (Exception $e) {
    error_log("Image deletion error: " . $e->getMessage());
    
    if ($isAjax) {
        sendJsonResponse(false, $e->getMessage());
    } else {
        // Fallback for non-AJAX requests
        $redirect = isset($animalId) ? "view_animal.php?id=$animalId&error=delete_failed" : 'dashboard.php';
        header("Location: $redirect");
        exit();
    }
}
