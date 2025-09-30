<?php
include 'includes/db.php';
include 'includes/auth.php';


// Always start session for POST/JSON API if you need user context (optional, but safe)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include the QR code library
require_once __DIR__ . '/qr/lib/full/qrlib.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get animal ID and updated form data
    $id = $_POST['id'];
    $type = $_POST['type'];
    $age = $_POST['age'];
    $breed = $_POST['breed'];
    $mark = $_POST['mark'];
    $breed_season = $_POST['breed_season'];
    $vaccination_date = $_POST['vaccination_date'];
    $vaccination_time = $_POST['vaccination_time'];

    // Fetch existing animal details from the database to check for changes
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
    $stmt->execute([$id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) {
        $_SESSION['error'] = "Animal not found!";
        header("Location: dashboard.php");
        exit();
    }

    // Check if any key details have changed that require a new QR code
    $qrCodeFile = $animal['qr_code']; // Keep existing QR code path if not changed
    if ($type != $animal['type'] || $age != $animal['age'] || $breed != $animal['breed'] || $mark != $animal['mark'] || $breed_season != $animal['breed_season']) {
        // Generate a new unique filename for the updated QR code
        $qrCodeFile = 'assets/images/' . uniqid() . '.png';

        // Generate QR code data string
        $qrData = "ID: $id, Type: $type, Age: $age days, Breed: $breed, Mark: $mark, Breed Season: $breed_season";

        // Generate and save the new QR code image
        QRcode::png($qrData, $qrCodeFile, QR_ECLEVEL_L, 3);

        // Optionally, delete the old QR code file to save storage
        if (file_exists($animal['qr_code'])) {
            unlink($animal['qr_code']);
        }
    }

    // Update animal details in the database, including vaccination date and time
    $stmt = $pdo->prepare("UPDATE animals SET type = ?, age = ?, breed = ?, mark = ?, breed_season = ?, qr_code = ?, vaccination_date = ?, vaccination_time = ? WHERE id = ?");
    $result = $stmt->execute([$type, $age, $breed, $mark, $breed_season, $qrCodeFile, $vaccination_date, $vaccination_time, $id]);

    if ($result) {
        $_SESSION['success'] = "Animal details updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update animal details. Please try again.";
    }

    // Redirect back to the dashboard
    header("Location: dashboard.php");
    exit();
}
?>
