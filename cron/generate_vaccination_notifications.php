<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/vaccination_notifications.php';

// Get animals due for vaccination
$stmt = $pdo->query("
    SELECT a.id, a.next_vaccination_date, a.vaccination_cycle_days, 
           u.id as user_id
    FROM animals a
    JOIN users u ON u.role = 'caretaker'  -- Assign to caretakers
    WHERE a.next_vaccination_date IS NOT NULL
    AND a.next_vaccination_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
");

$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($animals as $animal) {
    $dueDate = $animal['next_vaccination_date'];
    $type = $dueDate < date('Y-m-d') ? 'overdue' : 'due';
    
    // Check if notification already exists
    $stmt = $pdo->prepare("
        SELECT id FROM vaccination_notifications
        WHERE animal_id = ? AND due_date = ? AND status = 'pending'
    ");
    $stmt->execute([$animal['id'], $dueDate]);
    
    if (!$stmt->fetch()) {
        createVaccinationNotification($pdo, $animal['id'], $animal['user_id'], $dueDate, $type);
    }
}

// Log execution
file_put_contents(__DIR__ . '/cron.log', date('Y-m-d H:i:s') . " - Generated notifications\n", FILE_APPEND);