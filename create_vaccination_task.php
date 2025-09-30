<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $animalId = $data['animal_id'];
    $dueDate = $data['due_date'];
    $assignedTo = $data['assigned_to'];
    $taskDetails = $data['task_details'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (animal_id, task_type, task_details, due_date, assigned_to) 
                              VALUES (?, 'vaccination', ?, ?, ?)");
        $stmt->execute([$animal_id, $taskDetails, $dueDate, $assignedTo]);
        
        echo json_encode(['success' => true, 'message' => 'Vaccination task created successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>