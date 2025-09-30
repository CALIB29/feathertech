<?php
// Include database connection
include 'includes/db.php';

// Start the session
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get the form input values
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validate inputs
    $errors = [];

    // Check for empty fields
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $errors[] = 'All fields are required.';
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Check password length
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    // Validate role
    if (!in_array($role, ['user', 'admin', 'superadmin'])) {
        $errors[] = 'Invalid role selected.';
    }

    // If there are no validation errors, proceed with registration
    if (empty($errors)) {
        // Check if the email already exists in the database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Email already exists
            $_SESSION['error'] = 'Email is already registered.';
            header('Location: register.php');
            exit();
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert the new user into the database
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $email, $hashed_password, $role]);

        // Set success message
        $_SESSION['success'] = 'Registration successful! You can now log in.';
        header('Location: register.php');
        exit();
    } else {
        // If there are errors, store them in the session and redirect
        $_SESSION['errors'] = $errors;
        header('Location: register.php');
        exit();
    }
}
?>