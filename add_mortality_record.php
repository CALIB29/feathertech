<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id = $_POST['animal_id'];
    $death_date = $_POST['death_date'];
    $cause_of_death = $_POST['cause_of_death'];

    $stmt = $pdo->prepare("INSERT INTO mortality_records (animal_id, death_date, cause_of_death) VALUES (?, ?, ?)");
    $stmt->execute([$animal_id, $death_date, $cause_of_death]);

    header("Location: view_animal.php?id=$animal_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Mortality Record</title>
    <link rel="stylesheet" href="assets/css/style3.css"> <!-- Your CSS file -->
</head>
<body>
    <div class="container">
        <h1>Add Mortality Record</h1>
        <form method="POST" action="add_mortality_record.php">
            <input type="hidden" name="animal_id" value="<?= $_GET['animal_id'] ?>">
            <label>Death Date: <input type="date" name="death_date" required></label>
            <label>Cause of Death: <input type="text" name="cause_of_death" required></label>
            <button type="submit">Add Mortality Record</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>