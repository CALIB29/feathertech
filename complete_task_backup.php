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

// Enhanced debug logging
error_log("=== COMPLETE TASK DEBUG ===");
error_log("POST data received: " . json_encode($_POST));
error_log("FILES data received: " . json_encode($_FILES));
error_log("Raw task_id value: " . var_export($taskId, true));
error_log("task_id type: " . gettype($taskId));
error_log("task_id length: " . strlen($taskId ?? ''));
error_log("==========================");

// Handle file upload for proof (if any)
if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/proofs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileName = uniqid('proof_') . '_' . basename($_FILES['proof']['name']);
    $targetPath = $uploadDir . $fileName;

    // Validate file size (100MB limit for videos)
    $maxFileSize = 100 * 1024 * 1024; // 100MB in bytes
    if ($_FILES['proof']['size'] > $maxFileSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 100MB.']);
        exit;
    }

    // Get file extension
    $fileExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Allowed file types
    $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $allowedVideoTypes = ['mp4', 'avi', 'mov', 'wmv', 'webm'];

    $isImage = in_array($fileExtension, $allowedImageTypes);
    $isVideo = in_array($fileExtension, $allowedVideoTypes);

    if (!$isImage && !$isVideo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only image files (JPG, PNG, GIF) and video files (MP4, AVI, MOV, WMV, WebM) are allowed.']);
        exit;
    }

    // For images, validate with getimagesize
    if ($isImage) {
        $check = getimagesize($_FILES['proof']['tmp_name']);
        if ($check === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image file.']);
            exit;
        }
    }

    // Move the file
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error uploading file.']);
        exit;
    }

    $proofPath = $targetPath;
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

// Validate task_id more thoroughly
if (!$taskId || !is_numeric($taskId) || (int)$taskId <= 0) {
    error_log("=== VALIDATION FAILED ===");
    error_log("taskId value: " . var_export($taskId, true));
    error_log("is_numeric check: " . is_numeric($taskId));
    error_log("int conversion: " . (int)$taskId);
    error_log("POST array keys: " . implode(', ', array_keys($_POST)));
    error_log("========================");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task ID is required and must be a valid positive number']);
    exit;
}

$taskId = (int)$taskId; // Ensure it's an integer

// Handle file upload for proof (if any)
if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/proofs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileName = uniqid('proof_') . '_' . basename($_FILES['proof']['name']);
    $targetPath = $uploadDir . $fileName;

    // Validate file size (100MB limit for videos)
    $maxFileSize = 100 * 1024 * 1024; // 100MB in bytes
    if ($_FILES['proof']['size'] > $maxFileSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 100MB.']);
        exit;
    }

    // Get file extension
    $fileExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Allowed file types
    $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $allowedVideoTypes = ['mp4', 'avi', 'mov', 'wmv', 'webm'];

    $isImage = in_array($fileExtension, $allowedImageTypes);
    $isVideo = in_array($fileExtension, $allowedVideoTypes);

    if (!$isImage && !$isVideo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only image files (JPG, PNG, GIF) and video files (MP4, AVI, MOV, WMV, WebM) are allowed.']);
        exit;
    }

    // For images, validate with getimagesize
    if ($isImage) {
        $check = getimagesize($_FILES['proof']['tmp_name']);
        if ($check === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image file.']);
            exit;
        }
    }

    // Move the file
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error uploading file.']);
        exit;
    }

    $proofPath = $targetPath;
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

// Validate task_id more thoroughly
if (!$taskId || !is_numeric($taskId) || (int)$taskId <= 0) {
    error_log("Invalid task_id received: " . var_export($taskId, true));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task ID is required and must be a valid positive number']);
    exit;
}

$taskId = (int)$taskId; // Ensure it's an integer

try {
    $pdo->beginTransaction();

    // Update the task
    $stmt = $pdo->prepare("
        UPDATE vaccination_tasks
        SET status = 'completed',
            completed_at = NOW(),
            completed_by = ?,
            completion_notes = ?,
            proof_path = ?
        WHERE id = ?
    ");
    $stmt->execute([$userId, $notes, $proofPath, $taskId]);

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
