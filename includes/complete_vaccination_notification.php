<?php
// includes/complete_vaccination_notification.php
// Marks a vaccination notification as completed, stores proof image and notes

function completeVaccinationNotification($pdo, $notificationId, $proofImagePath, $notes = null) {
    $stmt = $pdo->prepare("
        UPDATE vaccination_notifications
        SET status = 'completed',
            proof_image = ?,
            notes = ?,
            completed_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$proofImagePath, $notes, $notificationId]);
}
