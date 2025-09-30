<?php
function sendVaccinationCompleteNotification($pdo, $notificationId) {
    // Get notification details
    $stmt = $pdo->prepare("
        SELECT vn.*, a.type, a.breed, u.email as user_email, 
               admin.email as admin_email
        FROM vaccination_notifications vn
        JOIN animals a ON vn.animal_id = a.id
        JOIN users u ON vn.user_id = u.id
        JOIN users admin ON admin.role = 'admin'
        WHERE vn.id = ?
    ");
    $stmt->execute([$notificationId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $to = $data['admin_email'];
        $subject = "Vaccination Completed - Animal #" . $data['animal_id'];
        $message = "
            <h2>Vaccination Completed</h2>
            <p><strong>Animal ID:</strong> {$data['animal_id']}</p>
            <p><strong>Type:</strong> {$data['type']}</p>
            <p><strong>Breed:</strong> {$data['breed']}</p>
            <p><strong>Completed by:</strong> {$data['user_email']}</p>
            <p><strong>Completed at:</strong> " . date('Y-m-d H:i') . "</p>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: no-reply@yourdomain.com\r\n";
        
        mail($to, $subject, $message, $headers);
    }
}
?>