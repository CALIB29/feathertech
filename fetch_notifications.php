<?php
header('Content-Type: application/json');

require 'includes/db.php';
require 'includes/auth.php';

try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    $userId = $_SESSION['user_id'];
    
    // Get the 10 most recent notifications for the user, prioritizing unread ones.
    $stmt = $pdo->prepare("
        SELECT id, message, is_read, created_at, related_type, related_id
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY is_read ASC, created_at DESC
        LIMIT 10
    ");
    $stmt->execute([':user_id' => $userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}