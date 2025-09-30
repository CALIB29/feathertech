<?php
// Helper functions for admin messaging system
function sendAdminMessage($pdo, $senderId, $receiverId, $message, $taskType = 'Vaccination', $dueDate = null) {
    $stmt = $pdo->prepare("INSERT INTO admin_messages (sender_id, receiver_id, message, task_type, due_date, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$senderId, $receiverId, $message, $taskType, $dueDate]);
    return $pdo->lastInsertId();
}

function getAdminMessages($pdo, $userId, $role) {
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT am.*, u.username AS sender_name FROM admin_messages am JOIN users u ON am.sender_id = u.id WHERE am.receiver_id = ? ORDER BY am.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($role === 'super admin') {
        $stmt = $pdo->prepare("SELECT am.*, u.username AS receiver_name FROM admin_messages am JOIN users u ON am.receiver_id = u.id WHERE am.sender_id = ? ORDER BY am.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

function completeAdminMessage($pdo, $messageId, $userId, $proofPath = null) {
    $stmt = $pdo->prepare("UPDATE admin_messages SET status = 'completed', proof = ?, completed_at = NOW() WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$proofPath, $messageId, $userId]);
    return $stmt->rowCount() > 0;
}
?>
