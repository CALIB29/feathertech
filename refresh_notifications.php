<?php
// refresh_notifications.php
// Returns updated vaccination notifications for the dashboard (AJAX)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once 'includes/db.php';
require_once 'includes/vaccination_notifications.php';

$response = ['success' => false, 'notifications' => []];

try {
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    $userId = $_SESSION['user_id'] ?? null;
    $notifications = getVaccinationNotifications($pdo, $userId, $isAdmin);
    $response['success'] = true;
    $response['notifications'] = $notifications;
} catch (Exception $e) {
    $response['message'] = 'Error fetching notifications: ' . $e->getMessage();
}

echo json_encode($response);
