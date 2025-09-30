<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id = $_POST['animal_id'];
    $feed_type = $_POST['feed_type'];
    $quantity = $_POST['quantity'];
    $feeding_time = $_POST['feeding_time'];

    $stmt = $pdo->prepare("INSERT INTO feeding_schedules (animal_id, feed_type, quantity, feeding_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$animal_id, $feed_type, $quantity, $feeding_time]);

    header("Location: view_animal.php?id=$animal_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Feeding Schedule</title>
    <link rel="stylesheet" href="assets/css/style3.css"> <!-- Your CSS file -->
</head>
<body>
    <div class="container">
        <h1>Add Feeding Schedule</h1>
        <form method="POST" action="add_feeding_schedule.php">
            <input type="hidden" name="animal_id" value="<?= $_GET['animal_id'] ?>">
            <label>Feed Type: <input type="text" name="feed_type" required></label>
            <label>Quantity (kg): <input type="number" step="0.01" name="quantity" required></label>
            <label>Feeding Time: <input type="time" name="feeding_time" required></label>
            <button type="submit">Add Feeding Schedule</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>