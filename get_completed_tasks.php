<?php
include 'includes/db.php';
include 'includes/auth.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only allow super admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get all completed vaccinations
    $stmt = $pdo->prepare("
        SELECT t.*, a.type as animal_type, a.breed as animal_breed, 
               u.username as completed_by_name, u.email as completed_by_email
        FROM vaccination_tasks t
        JOIN animals a ON t.animal_id = a.id
        LEFT JOIN users u ON t.completed_by = u.id
        WHERE t.status = 'completed'
        ORDER BY t.completed_date DESC
    ");
    $stmt->execute();
    $completedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tasks' => $completedTasks]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>