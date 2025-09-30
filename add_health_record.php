<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id = $_POST['animal_id'];
    $record_date = $_POST['record_date'];
    $vaccination = $_POST['vaccination'];
    $illness = $_POST['illness'];
    $treatment = $_POST['treatment'];
    $notes = $_POST['notes'];

    $stmt = $pdo->prepare("INSERT INTO health_records (animal_id, record_date, vaccination, illness, treatment, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$animal_id, $record_date, $vaccination, $illness, $treatment, $notes]);

    header("Location: view_animal.php?id=$animal_id");
    exit();
}
?>
<form method="POST" action="add_health_record.php">
    <input type="hidden" name="animal_id" value="<?= $_GET['animal_id'] ?>">
    <label>Record Date: <input type="date" name="record_date" required></label>
    <label>Vaccination: <input type="text" name="vaccination"></label>
    <label>Illness: <input type="text" name="illness"></label>
    <label>Treatment: <input type="text" name="treatment"></label>
    <label>Notes: <textarea name="notes"></textarea></label>
    <button type="submit">Add Record</button>
</form>