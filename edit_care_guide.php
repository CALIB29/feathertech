<?php
include 'includes/db.php';
session_start();

// Only allow super admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

$animalType = $_GET['type'] ?? '';
if (empty($animalType)) {
    header("Location: dashboard.php?error=invalid_type");
    exit();
}

// Fetch the current guide content
$stmt = $pdo->prepare("SELECT * FROM care_guides WHERE animal_type = ?");
$stmt->execute([$animalType]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    // Provide default values if no guide exists yet
    $guide = [
        'title' => ucfirst($animalType) . ' Care Guide',
        'content' => '<p>Default content for ' . htmlspecialchars($animalType) . '.</p>'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Care Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/35.4.0/classic/ckeditor.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2>Edit Care Guide for <?= htmlspecialchars(ucfirst($animalType)) ?></h2>
    <form action="save_care_guide.php" method="POST">
        <input type="hidden" name="animal_type" value="<?= htmlspecialchars($animalType) ?>">
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($guide['title']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="contentEditor" class="form-label">Content</label>
            <textarea id="contentEditor" name="content" class="form-control" rows="15"><?= htmlspecialchars($guide['content']) ?></textarea>
        </div>
        <script>
            ClassicEditor
                .create(document.querySelector('#contentEditor'))
                .catch(error => {
                    console.error(error);
                });
        </script>
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= htmlspecialchars($animalType) ?>_care.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
