<?php
function handleAvatarUpload($pdo, $userId) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return ['success' => false, 'error' => 'Invalid CSRF token'];
    }

    // Check if file was uploaded
    if (!isset($_FILES['avatar']) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }

    $file = $_FILES['avatar'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
    }

    // Validate file type and size
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and GIF files are allowed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size exceeds 2MB limit'];
    }

    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/avatars/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }

    // Update database
    try {
        $stmt = $pdo->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
        $stmt->execute([':avatar' => $destination, ':id' => $userId]);

        // Update session
        $_SESSION['avatar'] = $destination;

        return ['success' => true, 'url' => $destination];
    } catch (PDOException $e) {
        // Delete the uploaded file if database update fails
        unlink($destination);
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function getAvatar($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['avatar'] : '/assets/images/user-avatar.jpg';
    } catch (PDOException $e) {
        error_log("Avatar fetch error: " . $e->getMessage());
        return '/assets/images/user-avatar.jpg';
    }
}
?>