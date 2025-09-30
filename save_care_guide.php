<?php
include 'includes/db.php';
session_start();

// Only allow super admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animalType = $_POST['animal_type'] ?? '';
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $userId = $_SESSION['user_id'] ?? null;

    if (empty($animalType) || empty($title) || empty($content)) {
        header("Location: edit_care_guide.php?type={$animalType}&error=missing_fields");
        exit();
    }

    // Save the updated content to the database
    $stmt = $pdo->prepare("
        UPDATE care_guides 
        SET title = ?, content = ?, last_updated_by = ?
        WHERE animal_type = ?
    ");
    
    if ($stmt->execute([$title, $content, $userId, $animalType])) {
        header("Location: {$animalType}_care.php?success=updated");
    } else {
        header("Location: edit_care_guide.php?type={$animalType}&error=save_failed");
    }
} else {
    header("Location: dashboard.php");
}
?>
