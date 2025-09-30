<?php
include 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Animal ID not provided']);
    exit();
}

$animalId = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
    $stmt->execute([$animalId]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$animal) {
        echo json_encode(['error' => 'Animal not found']);
        exit();
    }
    
    echo json_encode([
        'id' => $animal['id'],
        'vaccination_date' => $animal['vaccination_date'],
        'vaccination_time' => $animal['vaccination_time']
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>