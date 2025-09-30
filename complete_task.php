<?php
include 'includes/db.php';
include 'includes/auth.php';

// Add vaccine type sanitization
function sanitizeVaccineType($vaccineType) {
    $maxLength = 100; // Conservative limit
    if (strlen($vaccineType) > $maxLength) {
        error_log("Vaccine type truncated during task completion: '" . substr($vaccineType, 0, 50) . "...'");
        return substr($vaccineType, 0, $maxLength);
    }
    return $vaccineType;
}

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$taskId = $_POST['task_id'] ?? null;
$messageId = $_POST['message_id'] ?? null;
$notes = $_POST['notes'] ?? '';
$vaccineType = $_POST['vaccine_type'] ?? null; // Get vaccine type if provided
$proofPath = null;

// Handle file upload for proof (if any)
if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/proofs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $filename = uniqid('proof_') . '_' . basename($_FILES['proof']['name']);
    $targetPath = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
        $proofPath = $targetPath;
    }
}

if ($messageId) {
    require_once 'includes/admin_messages.php';
    $success = completeAdminMessage($pdo, $messageId, $userId, $proofPath);
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Admin message completed']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to complete admin message']);
    }
    exit;
}

if (!$taskId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update the task
    $stmt = $pdo->prepare("
        UPDATE vaccination_tasks 
        SET status = 'completed',
            completed_at = NOW(),
            completed_by = ?,
            completion_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$userId, $notes, $taskId]);

    // Get task details for notification
    $stmt = $pdo->prepare("
        SELECT vt.*, a.type as animal_type 
        FROM vaccination_tasks vt
        JOIN animals a ON vt.animal_id = a.id
        WHERE vt.id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create notification for admin
    $message = "Vaccination task completed for {$task['animal_type']} (ID: {$task['animal_id']})";
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, related_type, related_id)
        VALUES (1, ?, 'task_completion', ?)
    ");
    $stmt->execute([$message, $taskId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Task completed successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>