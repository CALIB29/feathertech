<?php
require_once 'config/database.php';

header('Content-Type: application/json');
session_start();

try {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Invalid username or password');
    }

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
