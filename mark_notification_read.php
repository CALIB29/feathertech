<?php
header('Content-Type: application/json');

require 'includes/db.php';
require 'includes/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method", 405);
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception("Invalid CSRF token", 403);
    }

    // Get notification ID from POST data
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    
    if (!$notificationId) {
        throw new Exception("Invalid notification ID", 400);
    }
    
    $userId = $_SESSION['user_id'];
    
    // Debug logging
    error_log("Mark notification read attempt - UserID: $userId, NotificationID: $notificationId, CSRF: " . ($_POST['csrf_token'] ?? 'missing'));
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
        AND user_id = ?
        AND is_read = 0
    ");
    
    $success = $stmt->execute([$notificationId, $userId]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        // Log debug information
        error_log("Notification update failed - UserID: $userId, NotificationID: $notificationId, Rows affected: " . $stmt->rowCount());
        throw new Exception("Notification not found or already marked as read", 404);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}