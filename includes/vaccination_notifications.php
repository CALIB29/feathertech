<?php
function getVaccinationNotifications($pdo, $userId, $isAdmin = false) {
    try {
        $sql = "
            SELECT vn.*, a.type, a.breed, a.mark, 
                   DATEDIFF(vn.due_date, CURDATE()) as days_remaining
            FROM vaccination_notifications vn
            JOIN animals a ON vn.animal_id = a.id
            WHERE vn.status = 'pending'
            AND (vn.user_id = :user_id OR :is_admin = 1)
            ORDER BY 
                CASE WHEN vn.due_date < CURDATE() THEN 0 ELSE 1 END,
                vn.due_date ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':is_admin' => $isAdmin ? 1 : 0
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Notification fetch error: " . $e->getMessage());
        return [];
    }
}

function markNotificationAsComplete($pdo, $notificationId, $userId) {
    try {
        $pdo->beginTransaction();
        
        // Get notification details first
        $stmt = $pdo->prepare("
            SELECT animal_id, vaccination_type 
            FROM vaccination_notifications 
            WHERE id = ? AND (user_id = ? OR ? = 1)
        ");
        $stmt->execute([$notificationId, $userId, ($_SESSION['user_role'] ?? '') === 'admin' ? 1 : 0]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification) {
            throw new Exception("Notification not found or access denied");
        }
        
        // Update animal vaccination record
        $updateStmt = $pdo->prepare("
            UPDATE animals 
            SET vaccination_date = CURDATE(),
                vaccination_time = CURTIME(),
                vaccination_type = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $notification['vaccination_type'],
            $notification['animal_id']
        ]);
        
        // Mark notification as completed
        $completeStmt = $pdo->prepare("
            UPDATE vaccination_notifications 
            SET status = 'completed',
                completed_at = NOW(),
                completed_by = ?
            WHERE id = ?
        ");
        $completeStmt->execute([$userId, $notificationId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error completing notification: " . $e->getMessage());
        return false;
    }
}
?>