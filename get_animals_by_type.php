<?php
// get_animals_by_type.php
include 'includes/db.php';

$type = $_GET['type'] ?? '';
if (!$type) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, type, breed FROM animals WHERE type = ?");
$stmt->execute([$type]);
$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($animals);
