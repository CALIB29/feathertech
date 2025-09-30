<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hen_id = $_POST['hen_id'];
    $production_date = $_POST['production_date'];
    $eggs_collected = $_POST['eggs_collected'];

    $stmt = $pdo->prepare("INSERT INTO egg_production (hen_id, production_date, eggs_collected) VALUES (?, ?, ?)");
    $stmt->execute([$hen_id, $production_date, $eggs_collected]);

    header("Location: view_animal.php?id=$hen_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Egg Production</title>
    <link rel="stylesheet" href="assets/css/style3.css"> <!-- Your CSS file -->
</head>
<body>
    <div class="container">
        <h1>Add Egg Production</h1>
        <form method="POST" action="add_egg_production.php">
            <input type="hidden" name="hen_id" value="<?= $_GET['hen_id'] ?>">
            <label>Production Date: <input type="date" name="production_date" required></label>
            <label>Eggs Collected: <input type="number" name="eggs_collected" required></label>
            <button type="submit">Add Egg Production</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>