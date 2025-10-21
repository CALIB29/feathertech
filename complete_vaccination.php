<?php
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/vaccination_notifications.php';
require_once 'includes/complete_vaccination_notification.php'; // Ensure this file defines completeVaccinationNotification

// Send notification to admin
require 'includes/send_notification.php';
// Note: sendVaccinationCompleteNotification should be called after $notificationId is set

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['notification_id'])) {
    header("Location: dashboard.php");
    exit();
}

$notificationId = $_GET['notification_id'];

// Get notification details
$stmt = $pdo->prepare("
    SELECT vn.*, a.type, a.breed, a.mark, a.id as animal_id
    FROM vaccination_notifications vn
    JOIN animals a ON vn.animal_id = a.id
    WHERE vn.id = ? AND vn.status = 'pending'
");
$stmt->execute([$notificationId]);
$notification = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$notification) {
    $_SESSION['error'] = "Notification not found or already completed";
    header("Location: dashboard.php");
    exit();
}

// Now that $notificationId is set, send the notification to admin
sendVaccinationCompleteNotification($pdo, $notificationId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    try {
        // Validate and upload proof image/video
        if (empty($_FILES['proof_image']['name'])) {
            throw new Exception("Proof image/video is required");
        }

        $uploadDir = 'assets/vaccination_proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid('vaccine_') . '_' . basename($_FILES['proof_image']['name']);
        $targetFile = $uploadDir . $fileName;

        // Check file size (500MB max)
        $maxFileSize = 500 * 1024 * 1024; // 500MB in bytes
        if ($_FILES['proof_image']['size'] > $maxFileSize) {
            throw new Exception("File is too large (max 500MB)");
        }

        // Allow certain file formats (images and videos)
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov', 'wmv', 'webm'];
        if (!in_array($imageFileType, $allowedExtensions)) {
            throw new Exception("Only JPG, JPEG, PNG, GIF, MP4, AVI, MOV, WMV, WEBM files are allowed");
        }

        if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetFile)) {
            throw new Exception("Error uploading file");
        }

        // Complete the notification
        $notes = $_POST['notes'] ?? null;
        if (completeVaccinationNotification($pdo, $notificationId, $targetFile, $notes)) {
            // Update animal's next vaccination date
            $stmt = $pdo->prepare("
                UPDATE animals 
                SET vaccination_date = CURDATE(),
                    vaccination_time = CURTIME(),
                    next_vaccination_date = DATE_ADD(CURDATE(), INTERVAL vaccination_cycle_days DAY)
                WHERE id = ?
            ");
            $stmt->execute([$notification['animal_id']]);

            $_SESSION['success'] = "Vaccination completed successfully!";
            header("Location: view_animal.php?id=" . $notification['animal_id']);
            exit();
        } else {
            throw new Exception("Error completing vaccination");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Vaccination</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .proof-preview {
            max-width: 100%;
            max-height: 300px;
            display: none;
            margin-top: 15px;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-syringe me-2"></i> Complete Vaccination</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h5>Animal Details</h5>
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <strong>ID:</strong> <?= $notification['animal_id'] ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>Type:</strong> <?= $notification['type'] ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>Breed:</strong> <?= $notification['breed'] ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>Mark:</strong> <?= $notification['mark'] ?>
                                </li>
                            </ul>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="proof_image" class="form-label">
                                    <i class="fas fa-camera me-1"></i> Vaccination Proof (Image/Video)
                                </label>
                                <input class="form-control" type="file" id="proof_image" name="proof_image" required
                                       accept="image/*,video/*" capture="environment">
                                <img id="proofPreview" src="#" class="proof-preview" alt="Proof preview">
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-notes-medical me-1"></i> Additional Notes
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle me-1"></i> Mark as Completed
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times-circle me-1"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Preview image/video before upload
        document.getElementById('proof_image').addEventListener('change', function(e) {
            const preview = document.getElementById('proofPreview');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.style.display = 'block';
                preview.src = e.target.result;
            }
            
            if (file) {
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>