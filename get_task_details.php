<?php
include 'includes/db.php';
include 'includes/auth.php';

header('Content-Type: application/json');

$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT vt.*, u.name as completed_by_name
        FROM vaccination_tasks vt
        LEFT JOIN users u ON vt.completed_by = u.id
        WHERE vt.id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    echo json_encode($task);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>