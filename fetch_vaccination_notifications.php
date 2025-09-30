<?php
include 'includes/db.php';

$stmt = $pdo->query("SELECT * FROM animals WHERE vaccination_date = CURDATE()");
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($vaccinations);
?>