<?php
require 'includes/db.php';

try {
    // Get due and overdue notifications
    $query = "
        SELECT vn.*, a.vaccination_type, a.vaccination_date, u.email
        FROM vaccination_notifications vn
        JOIN animals a ON vn.animal_id = a.id
        JOIN users u ON vn.user_id = u.id
        WHERE vn.is_read = FALSE
        AND vn.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ORDER BY vn.due_date ASC
    ";
    
    $stmt = $pdo->query($query);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($notifications as $notification) {
        $dueDate = new DateTime($notification['due_date']);
        $today = new DateTime();
        $interval = $today->diff($dueDate);
        $daysDiff = (int)$interval->format('%r%d');
        
        // Determine notification type
        if ($daysDiff < 0) {
            $type = 'overdue';
            $subject = "URGENT: Vaccination Overdue for Animal ID {$notification['animal_id']}";
            $message = "The vaccination for animal ID {$notification['animal_id']} is overdue by " . abs($daysDiff) . " days!";
        } elseif ($daysDiff === 0) {
            $type = 'due';
            $subject = "Vaccination Due Today for Animal ID {$notification['animal_id']}";
            $message = "The vaccination for animal ID {$notification['animal_id']} is due today!";
        } else {
            $type = 'reminder';
            $subject = "Vaccination Reminder: Due in {$daysDiff} days for Animal ID {$notification['animal_id']}";
            $message = "The vaccination for animal ID {$notification['animal_id']} is due in {$daysDiff} days.";
        }
        
        // Update notification type if changed
        if ($notification['notification_type'] !== $type) {
            $stmt = $pdo->prepare("
                UPDATE vaccination_notifications 
                SET notification_type = :type 
                WHERE id = :id
            ");
            $stmt->execute([
                ':type' => $type,
                ':id' => $notification['id']
            ]);
        }
        
        // Send email (implement your email sending function)
        sendNotificationEmail($notification['email'], $subject, $message);
        
        // Log the reminder
        error_log("Sent {$type} notification for animal ID {$notification['animal_id']} to {$notification['email']}");
    }
    
    echo "Processed " . count($notifications) . " vaccination reminders\n";
    
} catch (Exception $e) {
    error_log("Error in vaccination reminders: " . $e->getMessage());
}

function sendNotificationEmail($to, $subject, $message) {
    // Implement your email sending logic here
    // This could use PHPMailer, mail(), or your preferred email service
    // For now, we'll just log it
    error_log("Would send email to {$to} with subject: {$subject}");
}