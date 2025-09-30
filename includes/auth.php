<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php'; // Ensure this line includes db.php, which sets up $pdo

function login($username, $password) {
    global $pdo;
    
    // Check if username or password is empty
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username and password.';
        return false;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Store user data in the session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username; // Store username in session
        $_SESSION['role'] = $user['role']; // Store user role in session
        return true;
    } else {
        // Set error message in session
        $_SESSION['error'] = 'Invalid username or password.';
        return false;
    }
}

function register($username, $password) {
    global $pdo;
    
    // Check if username or password is empty
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username and password.';
        return false;
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Username already exists.';
        return false;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    if ($stmt->execute([$username, $passwordHash])) {
        return true;
    } else {
        $_SESSION['error'] = 'Registration failed. Please try again.';
        return false;
    }
}

function logout() {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>