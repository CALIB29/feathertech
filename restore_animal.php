<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        // Validate session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_secret_user'])) {
            $_SESSION['error'] = "Unauthorized access";
            header("Location: archive.php");
            exit();
        }

        $pdo->beginTransaction();

        // Get archived record
        $stmt = $pdo->prepare("SELECT * FROM animal_archive WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$animal) {
            throw new Exception("Record not found in archive");
        }

        // Restore to main table
        $restoreStmt = $pdo->prepare("
            INSERT INTO animals 
            (id, type, age, breed, mark, status, vaccination_date, vaccination_time, qr_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $restoreStmt->execute([
            $animal['id'],
            $animal['type'],
            $animal['age'],
            $animal['breed'],
            $animal['mark'],
            $animal['status'],
            $animal['vaccination_date'],
            $animal['vaccination_time'],
            $animal['qr_code']
        ]);

        // Delete from archive
        $deleteStmt = $pdo->prepare("DELETE FROM animal_archive WHERE id = ?");
        $deleteStmt->execute([$_GET['id']]);

        $pdo->commit();
        
        $_SESSION['success'] = "Record restored successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: archive.php");
    exit();
}

// Invalid access
header("Location: archive.php");
exit();
?>