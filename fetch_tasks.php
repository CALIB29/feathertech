<?php
include 'includes/db.php';
include 'includes/auth.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT vt.*, a.type as animal_type, u.name as completed_by_name
        FROM vaccination_tasks vt
        LEFT JOIN animals a ON vt.animal_id = a.id
        LEFT JOIN users u ON vt.completed_by = u.id
        WHERE vt.status != 'completed' OR vt.completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY 
            CASE 
                WHEN vt.status = 'pending' AND vt.due_date < CURDATE() THEN 0
                WHEN vt.status = 'pending' THEN 1
                ELSE 2
            END,
            vt.due_date ASC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($tasks);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>