
<?php
// upload_avatar.php: Handles animal image upload and updates DB for animals
header('Content-Type: application/json');
session_start();
include 'includes/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$animal_id = isset($_POST['animal_id']) ? intval($_POST['animal_id']) : 0;
if ($animal_id <= 0) {
    $error = 'Invalid animal ID.';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['error' => $error]);
    } else {
        $_SESSION['error'] = $error;
        header('Location: dashboard.php');
    }
    exit();
}

// Remove image logic
if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
    try {
        $stmt = $pdo->prepare('SELECT image FROM animals WHERE id = ?');
        $stmt->execute([$animal_id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists(__DIR__ . '/' . $old) && strpos($old, 'uploads/avatars/') === 0) {
            @unlink(__DIR__ . '/' . $old);
        }
        $stmt = $pdo->prepare('UPDATE animals SET image = NULL WHERE id = ?');
        $stmt->execute([$animal_id]);
        
        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => 'Animal image removed successfully!']);
        } else {
            $_SESSION['success'] = 'Animal image removed successfully!';
            header('Location: view_animal.php?id=' . $animal_id);
        }
    } catch (Exception $e) {
        $error = 'Failed to remove image: ' . $e->getMessage();
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(['error' => $error]);
        } else {
            $_SESSION['error'] = $error;
            header('Location: view_animal.php?id=' . $animal_id);
        }
    }
    exit();
}

// Handle file upload
if (!isset($_FILES['animal_image']) || $_FILES['animal_image']['error'] !== UPLOAD_ERR_OK) {
    $error = 'No image uploaded or upload error.';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['error' => $error]);
    } else {
        $_SESSION['error'] = $error;
        header('Location: view_animal.php?id=' . $animal_id);
    }
    exit();
}

$file = $_FILES['animal_image'];
$allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/jpg' => 'jpg'];
$max_size = 50 * 1024 * 1024; // 50MB for avatar images

// Validate file type
if (!array_key_exists($file['type'], $allowed_types)) {
    $error = 'Invalid file type. Only JPG and PNG allowed.';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['error' => $error]);
    } else {
        $_SESSION['error'] = $error;
        header('Location: view_animal.php?id=' . $animal_id);
    }
    exit();
}

// Validate file size
if ($file['size'] > $max_size) {
    $error = 'File too large. Max 15MB.';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['error' => $error]);
    } else {
        $_SESSION['error'] = $error;
        header('Location: view_animal.php?id=' . $animal_id);
    }
    exit();
}

try {
    // Prepare upload directory
    $upload_dir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename with proper extension
    $ext = $allowed_types[$file['type']];
    $filename = 'animal_' . $animal_id . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $filename;
    $db_path = 'uploads/avatars/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Optionally, delete old image if exists
    $stmt = $pdo->prepare('SELECT image FROM animals WHERE id = ?');
    $stmt->execute([$animal_id]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists(__DIR__ . '/' . $old) && strpos($old, 'uploads/avatars/') === 0) {
        @unlink(__DIR__ . '/' . $old);
    }

    // Update DB
    $stmt = $pdo->prepare('UPDATE animals SET image = ? WHERE id = ?');
    $stmt->execute([$db_path, $animal_id]);

    if ($isAjax) {
        echo json_encode([
            'success' => true, 
            'message' => 'Image uploaded successfully!',
            'image_path' => $db_path . '?t=' . time()
        ]);
    } else {
        $_SESSION['success'] = 'Image uploaded successfully!';
        header('Location: view_animal.php?id=' . $animal_id . '&upload=success');
    }
} catch (Exception $e) {
    $error = 'Error uploading image: ' . $e->getMessage();
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['error' => $error]);
    } else {
        $_SESSION['error'] = $error;
        header('Location: view_animal.php?id=' . $animal_id);
    }
}

exit();