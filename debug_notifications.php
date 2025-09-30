<?php
// Debug script to check notification data
require 'includes/db.php';
require 'includes/auth.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

$userId = $_SESSION['user_id'];

echo "<h2>Debug: Current User Notifications</h2>";
echo "User ID: $userId<br><br>";

// Check if notifications table exists and has data
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() === 0) {
        echo "❌ notifications table does not exist<br>";
        exit;
    }

    echo "✅ notifications table exists<br><br>";

    // Check for unread notifications
    $stmt = $pdo->prepare("
        SELECT id, message, is_read, created_at, user_id
        FROM notifications
        WHERE user_id = ?
        AND is_read = 0
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($notifications)) {
        echo "No unread notifications found for user ID: $userId<br><br>";

        // Show all notifications for this user
        $stmt = $pdo->prepare("
            SELECT id, message, is_read, created_at, user_id
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $allNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allNotifications)) {
            echo "No notifications at all for this user.<br>";
        } else {
            echo "All notifications for this user:<br>";
            foreach ($allNotifications as $notif) {
                echo "ID: {$notif['id']}, Read: " . ($notif['is_read'] ? 'Yes' : 'No') . ", Message: " . substr($notif['message'], 0, 50) . "...<br>";
            }
        }
    } else {
        echo "Found unread notifications:<br>";
        foreach ($notifications as $notif) {
            echo "ID: {$notif['id']}, Message: " . substr($notif['message'], 0, 50) . "...<br>";
        }
    }

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}
?>
