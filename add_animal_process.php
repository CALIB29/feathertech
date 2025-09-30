<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
include 'includes/db.php';
include 'includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate input data
    $type = trim($_POST['type']);
    $gender = trim($_POST['gender']); // Add this line
    $age = intval($_POST['age']);
    $breed_dropdown = trim($_POST['breed_dropdown']);
    $breed_nickname = trim($_POST['breed_nickname']);

    $breed = $breed_dropdown;
    if (!empty($breed_nickname)) {
        $breed .= ' ' . $breed_nickname;
    }
    $mark = trim($_POST['mark']);
    $breed_season = trim($_POST['breed_season']);

    // Validation
    $errors = [];
    if (empty($type)) $errors[] = 'Type is required.';
    if (empty($gender)) $errors[] = 'Gender is required.';
    if ($age <= 0) $errors[] = 'Please enter a valid age.';
    if (empty($breed)) $errors[] = 'Breed is required.';
    if (empty($mark)) $errors[] = 'Mark is required.';
    if (empty($breed_season)) $errors[] = 'Breed season is required.';

    // Professional logic: Rooster must be male, Hen must be female, Chick can be either
    if ($type === 'Rooster' && strtolower($gender) !== 'male') {
        $errors[] = 'You selected the wrong gender for a Rooster. Please try again and use your brain.';
    }
    if ($type === 'Hen' && strtolower($gender) !== 'female') {
        $errors[] = 'You selected the wrong gender for a Hen. Please try again and use your brain.';
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: add_animal.php");
        exit();
    }

    try {
        // Prepare and execute SQL statement
        $stmt = $pdo->prepare("INSERT INTO animals (type, gender, age, breed, mark, breed_season) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $gender, $age, $breed, $mark, $breed_season]);

        // Get the last inserted animal ID
        $animal_id = $pdo->lastInsertId();

        // QR code generation
        include 'qr/lib/full/qrlib.php';

        $qr_data = "ID: $animal_id, Type: $type, Age: $age, Breed: $breed";
        $qr_filename = "assets/images/$animal_id.png";
        
        QRcode::png($qr_data, $qr_filename, QR_ECLEVEL_L, 4);
        
        // Update the animal's record with the QR code path
        $stmt = $pdo->prepare("UPDATE animals SET qr_code = ? WHERE id = ?");
        $stmt->execute([$qr_filename, $animal_id]);

        // Success message and redirect
        $_SESSION['success'] = 'Animal added successfully!';
        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        // Log the error for further debugging
        error_log("Error adding animal: " . $e->getMessage());
        
        $_SESSION['error'] = 'An error occurred while adding the animal. Please try again.';
        header("Location: add_animal.php");
        exit();
    }
} else {
    header("Location: add_animal.php");
    exit();
}
?>