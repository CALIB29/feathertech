<?php
include 'includes/db.php';
session_start();

$animalType = 'hen';

// Fetch care guide content from the database
$stmt = $pdo->prepare("SELECT * FROM care_guides WHERE animal_type = ?");
$stmt->execute([$animalType]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

$title = $guide['title'] ?? 'Hen Care Guide';
$content = $guide['content'] ?? '<p>Content not available.</p>';
$isSuperAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super admin');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fff0f6 60%, #ffb3c6 100%);
            min-height: 100vh;
        }
        .care-container {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 24px 16px 32px 16px;
            margin-top: 32px;
        }
        .care-header img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }
        .care-header h1 {
            font-size: 1.7rem;
            font-weight: 700;
            color: #ff69b4;
        }
        .care-header .text-muted {
            font-size: 1rem;
        }
        .alert {
            font-size: 0.98rem;
            border-radius: 12px;
            margin-bottom: 12px;
        }
        .care-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 28px;
        }
        .care-footer .btn {
            font-size: 1rem;
            border-radius: 10px;
            padding: 8px 18px;
        }
        .care-footer .badge {
            font-size: 1.3rem;
            padding: 10px 16px;
            border-radius: 16px;
        }
        @media (max-width: 600px) {
            .care-container {
                max-width: 98vw;
                margin-top: 10vw;
                padding: 16px 4vw 24px 4vw;
            }
            .care-header h1 {
                font-size: 1.2rem;
            }
            .care-header img {
                width: 48px;
                height: 48px;
            }
            .care-footer .badge {
                font-size: 1.1rem;
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
<div class="care-container">
    <div class="care-header text-center mb-4">
        <img src="assets/images/13_acf3092d55de05d63ce6ae348020dd7c.png" alt="Hen" class="mb-2">
        <h1><?= htmlspecialchars($title) ?></h1>
        <div class="text-muted">Stage: 7+ months</div>
    </div>
    <div class="mb-4">
        <?= $content ?>
    </div>
    <?php if ($isSuperAdmin): ?>
    <div class="text-center my-3">
        <a href="edit_care_guide.php?type=<?= $animalType ?>" class="btn btn-secondary"><i class="fas fa-edit me-1"></i> Edit Guide</a>
    </div>
    <?php endif; ?>
    <div class="care-footer">
        <a href="view_animal.php?id=<?= htmlspecialchars($_GET['id'] ?? '') ?>" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Animal</a>
        <span class="badge bg-danger text-white">🐔</span>
    </div>
</div>
</body>
</html>
