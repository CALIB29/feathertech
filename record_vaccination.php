<?php
// record_vaccination.php
// Handles AJAX vaccination completion from dashboard modal (with proof image upload)

session_start();
header('Content-Type: application/json');

require_once 'includes/db.php';
require_once 'includes/vaccination_notifications.php';

$response = ['success' => false];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    if (empty($_POST['notificationId']) && empty($_POST['notification_id'])) {
        throw new Exception('Missing notification ID');
    }
    $notificationId = $_POST['notificationId'] ?? $_POST['notification_id'];
    $notes = $_POST['notes'] ?? '';
    $userId = $_SESSION['user_id'];

    // Proof image validation
    if (empty($_FILES['proof_image']['name'])) {
        throw new Exception('Proof image is required');
    }
    $uploadDir = 'assets/vaccination_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $fileName = uniqid('vaccine_') . '_' . basename($_FILES['proof_image']['name']);
    $targetFile = $uploadDir . $fileName;
    $check = getimagesize($_FILES['proof_image']['tmp_name']);
    if ($check === false) {
        throw new Exception('File is not an image');
    }
    if ($_FILES['proof_image']['size'] > 5000000) {
        throw new Exception('File is too large (max 5MB)');
    }
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        throw new Exception('Only JPG, JPEG, PNG & GIF files are allowed');
    }
    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetFile)) {
        throw new Exception('Error uploading file');
    }

    // Mark notification as complete and update animal record
    require_once 'includes/complete_vaccination_notification.php';
    // Ensure the function is defined after including the file
    if (!function_exists('completeVaccinationNotification')) {
        throw new Exception('Function completeVaccinationNotification not found in includes/complete_vaccination_notification.php');
    }
    if (!completeVaccinationNotification($pdo, $notificationId, $targetFile, $notes)) {
        throw new Exception('Error completing vaccination');
    }

    // Optionally update animal's next vaccination date (if needed)
    $stmt = $pdo->prepare('SELECT animal_id FROM vaccination_notifications WHERE id = ?');
    $stmt->execute([$notificationId]);
    $row = $stmt->fetch();
    if ($row) {
        $animalId = $row['animal_id'];
        $pdo->prepare('UPDATE animals SET vaccination_date = CURDATE(), vaccination_time = CURTIME(), next_vaccination_date = DATE_ADD(CURDATE(), INTERVAL vaccination_cycle_days DAY) WHERE id = ?')->execute([$animalId]);

        // Insert into health_records with proof image
        $stmt2 = $pdo->prepare("INSERT INTO health_records (animal_id, record_date, vaccination, notes, proof_image) VALUES (?, NOW(), ?, ?, ?)");
        $stmt2->execute([$animalId, $_POST['vaccineName'] ?? 'Vaccination', $notes, $targetFile]);
    }

    $response['success'] = true;
    $response['message'] = 'Vaccination recorded successfully!';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
