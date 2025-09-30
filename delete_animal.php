<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $pdo->beginTransaction();

        // Get animal data
        $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        // Archive the record
        $archiveStmt = $pdo->prepare("
            INSERT INTO animal_archive 
            (id, type, age, breed, mark, status, vaccination_date, vaccination_time, qr_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $archiveStmt->execute([
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

        // Delete from main table
        $deleteStmt = $pdo->prepare("DELETE FROM animals WHERE id = ?");
        $deleteStmt->execute([$_GET['id']]);

        $pdo->commit();
        $_SESSION['success'] = "Record archived successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error archiving record: " . $e->getMessage();
    }
    header("Location: dashboard.php");
    exit();
}
?>