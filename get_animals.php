<?php
// get_animals.php - API endpoint for fetching animals by type
include 'includes/db.php';
include 'includes/auth.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated and has appropriate permissions
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $type = $_GET['type'] ?? '';
    
    if (empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Animal type is required']);
        exit();
    }

    // Fetch animals by type
    $stmt = $pdo->prepare("
        SELECT id, type, breed, mark, gender 
        FROM animals 
        WHERE type = :type 
        ORDER BY breed, id
    ");
    $stmt->execute([':type' => $type]);
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'animals' => $animals
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>