<?php
include 'includes/db.php';
// include 'includes/growth_tracker.php'; // GrowthTracker class not found or not needed

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=invalid_id");
    exit();
}

$animalId = (int)$_GET['id'];

// Fetch animal data with prepared statement
$stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
$stmt->execute([$animalId]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch vaccination history
$historyStmt = $pdo->prepare("SELECT * FROM vaccination_history WHERE animal_id = :animal_id ORDER BY vaccination_date DESC");
$historyStmt->execute([':animal_id' => $animalId]);
$vaccinationHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch health records
$health_stmt = $pdo->prepare("SELECT * FROM health_records WHERE animal_id = ? ORDER BY record_date DESC");
$health_stmt->execute([$animalId]);
$health_records = $health_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$animal) {
    header("Location: dashboard.php?error=animal_not_found");
    exit();
}

// Set default timestamps if not available
$animal['last_modified'] = $animal['last_modified'] ?? time();
$animal['last_updated'] = $animal['last_updated'] ?? date('Y-m-d H:i:s');

// Use the 'age' field from the database for consistency with dashboard.php
$animal['days_in_stage'] = !empty($animal['stage_transition_date']) ? 
    floor((time() - strtotime($animal['stage_transition_date'])) / 86400) : 0;
// $animal['age'] is already set from the DB
$readinessPercentage = calculateReadinessPercentage(
    $animal['growth_stage'] ?? 'chick', 
    $animal['age'],
    $animal['days_in_stage']
);

// Determine display values
$growthInfo = getGrowthStageInfo($animal);
$displayStage = getDisplayStage($animal);
$badgeClass = $growthInfo['badgeClass'];
$statusDetails = getStatusDetails($animal['growth_stage'] ?? 'chick', $animal['status'] ?? 'Not Yet Ready');

// Determine if this is a hen (show egg production)
$isHen = false;
if (isset($animal['type']) && stripos($animal['type'], 'hen') !== false) {
    $isHen = true;
}

// Generate view URL for QR code
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$viewUrl = $protocol . $host . '/view_animal_qr.php?id=' . $animalId;

// Handle QR code path
if (!empty($animal['qr_code'])) {
    $qrPath = parse_url($animal['qr_code'], PHP_URL_PATH) ?? $animal['qr_code'];
    $animal['qr_code_clean'] = $qrPath;
    $animal['qr_code_full'] = $qrPath . '?t=' . $animal['last_modified'];
} else {
    $animal['qr_code_clean'] = null;
    $animal['qr_code_full'] = null;
}


function getGrowthStageInfo($animal) {
    $age = (int)($animal['age'] ?? 0);
    $gender = strtolower($animal['gender'] ?? 'male');
    $gender = ($gender === 'male' || $gender === 'm' || $gender === '♂') ? 'male' : (($gender === 'female' || $gender === 'f' || $gender === '♀') ? 'female' : $gender);
    $stages = [
        'chick' => 0,
        'stag' => 150,    // male only
        'pullet' => 150,  // female only
        'rooster' => 270, // male only
        'hen' => 210      // female only
    ];
    $info = [
        'stage' => 'chick',
        'badgeClass' => 'badge-chick',
        'progress' => 0,
        'progressLabel' => 'Chick Progress',
        'progressBarClass' => 'chick',
        'progressBarStyle' => 'background: linear-gradient(90deg, #ffe066 0%, #fffbe7 100%);',
        'icon' => '<i class="fas fa-egg" style="color:#ffeb3b;"></i>',
    ];
    if ($gender === 'male') {
        if ($age < $stages['stag']) {
            $info['stage'] = 'chick';
            $info['badgeClass'] = 'badge-chick';
            $info['progressLabel'] = 'Chick Progress';
            $info['progress'] = min(100, round(($age / $stages['stag']) * 100, 1));
            $info['progressBarClass'] = 'chick';
            $info['progressBarStyle'] = 'background: linear-gradient(90deg, #ffe066 0%, #fffbe7 100%);';
            $info['icon'] = '<i class="fas fa-egg" style="color:#ffeb3b;"></i>';
        } elseif ($age < $stages['rooster']) {
            $info['stage'] = 'stag';
            $info['badgeClass'] = 'badge-stag';
            $info['progressLabel'] = 'Stag Progress';
            $info['progress'] = min(100, round((($age - $stages['stag']) / ($stages['rooster'] - $stages['stag'])) * 100, 1));
            $info['progressBarClass'] = 'stag';
            $info['progressBarStyle'] = 'background: linear-gradient(90deg, #2196f3 0%, #b3e5fc 100%);';
            $info['icon'] = '<i class="fas fa-kiwi-bird" style="color:#2196f3;"></i>';
        } else {
            $info['stage'] = 'rooster';
            $info['badgeClass'] = 'badge-rooster';
            $info['progressLabel'] = 'Rooster (Mature)';
            $info['progress'] = 100;
            $info['progressBarClass'] = 'rooster';
            $info['progressBarStyle'] = 'background: linear-gradient(90deg, #ff1744 0%, #ff8a80 100%);';
            $info['icon'] = '<i class="fas fa-fire" style="color:#ff1744;"></i>';
        }
    } elseif ($gender === 'female') {
        if ($age < $stages['pullet']) {
            $info['stage'] = 'chick';
            $info['badgeClass'] = 'badge-chick';
            $info['progressLabel'] = 'Chick Progress';
            $info['progress'] = min(100, round(($age / $stages['pullet']) * 100, 1));
            $info['progressBarClass'] = 'chick';
            $info['progressBarStyle'] = 'background: linear-gradient(90deg, #ffe066 0%, #fffbe7 100%);';
            $info['icon'] = '<i class="fas fa-egg" style="color:#ffeb3b;"></i>';
        } elseif ($age < $stages['hen']) {
            $info['stage'] = 'pullet';
            $info['badgeClass'] = 'badge-pullet';
            $info['progressLabel'] = 'Pullet Progress';
            $info['progress'] = min(100, round((($age - $stages['pullet']) / ($stages['hen'] - $stages['pullet'])) * 100, 1));
            $info['progressBarClass'] = 'pullet';
            $info['progressBarStyle'] = 'background: linear-gradient(90deg, #ffb74d 0%, #fff3e0 100%);';
            $info['icon'] = '<i class="fas fa-dove" style="color:#ffb74d;"></i>';
        } else {
            $info['stage'] = 'hen';
            $info['badgeClass'] = 'badge-hen';
            $info['progressLabel'] = 'Hen (Mature)';
            $info['progress'] = 100;
            $info['progressBarClass'] = 'hen';
            $info['progressBarStyle'] = 'background: linear-gradient(90deg, #43a047 0%, #b9f6ca 100%);';
            $info['icon'] = '<i class="fas fa-leaf" style="color:#43a047;"></i>';
        }
    }
    return $info;
}

function calculateReadinessPercentage($animal) {
    $growth = getGrowthStageInfo($animal);
    return $growth['progress'];
}

function getStatusDetails($growthStage, $status) {
    $statusMap = [
        'Ready for Harvesting' => [
            'color' => 'success',
            'icon' => 'fa-check-circle',
            'description' => 'Ready for processing'
        ],
        'Ready for Breeding' => [
            'color' => 'info',
            'icon' => 'fa-heart',
            'description' => 'Ready for mating'
        ],
        'Ready for Conditioning' => [
            'color' => 'warning',
            'icon' => 'fa-dumbbell',
            'description' => 'Ready for physical preparation'
        ],
        'Not Yet Ready' => [
            'color' => 'secondary',
            'icon' => 'fa-clock',
            'description' => 'Still developing'
        ],
        'default' => [
            'color' => 'light',
            'icon' => 'fa-question-circle',
            'description' => 'Status unknown'
        ]
    ];
    
    if ($growthStage === 'hen' && $status === 'Ready for Breeding') {
        $statusMap['Ready for Breeding']['description'] = 'Ready for egg production';
    }
    
    return $statusMap[$status] ?? $statusMap['default'];
}


// Returns display name for stage
function getDisplayStage($animal) {
    $growth = getGrowthStageInfo($animal);
    if ($growth['stage'] === 'pullet') return 'Pullet';
    return ucfirst($growth['stage']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poultry ID <?= htmlspecialchars($animal['id']) ?> | FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?php if (!empty($animal['qr_code'])): ?>
    <link rel="preload" href="<?= htmlspecialchars($animal['qr_code']) ?>" as="image">
    <?php endif; ?>
    <style>
        /* Toast Notifications */
        .toast {
            background: white;
            color: #333;
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            margin-bottom: 10px;
            max-width: 90%;
            width: auto;
            min-width: 300px;
            box-sizing: border-box;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .toast i {
            font-size: 20px;
        }
        
        .toast.success {
            background: var(--success-color);
            color: white;
        }
        
        .toast.error {
            background: var(--danger-color);
            color: white;
        }
        
        .toast.info {
            background: var(--info-color);
            color: white;
        }
        
        .toast.warning {
            background: var(--warning-color);
            color: white;
        }
        
        :root {
            --primary-color: #FFA726;
            --secondary-color: #5D4037;
            --accent-color: #4CAF50;
            --light-color: #FFF8E1;
            --dark-color: #3E2723;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --danger-color: #F44336;
            --info-color: #2196F3;
            --chick-color: #FFD166;
            --stag-color: #06D6A0;
            --rooster-color: #EF476F;
            --hen-color: #FF9A8B;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            padding: 0;
            margin: 0;
            min-height: 100vh;
        }
        
        .app-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            overflow-x: hidden;
        }
        
        .app-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .app-header::before {
            content: "";
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .app-header::after {
            content: "";
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .app-title {
            font-weight: 600;
            margin: 0;
            font-size: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .app-subtitle {
            font-weight: 300;
            margin: 5px 0 0;
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .poultry-id {
            background: white;
            color: var(--secondary-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        
        .content-section {
            padding: 20px;
        }
        
        .section-title {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 1.2rem;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .info-card.highlight {
            background: var(--light-color);
            border-left-color: var(--accent-color);
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--secondary-color);
            min-width: 120px;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-weight: 400;
            color: #555;
            flex: 1;
            font-size: 0.9rem;
        }
        
        .qr-section {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .qr-image {
            width: 180px;
            height: 180px;
            margin: 0 auto 15px;
            border: 1px solid #eee;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .btn-action {
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #FB8C00;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4E342E;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #3d8b40;
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }
        
        .last-updated {
            font-size: 0.8rem;
            color: #888;
            margin-top: 10px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            padding: 8px 15px;
            border-radius: 8px;
            background: #f5f5f5;
            transition: all 0.2s;
        }
        
        .back-button:hover {
            background: #eee;
            transform: translateY(-2px);
        }
        
        /* Poultry animation */
        .poultry-animation {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="%23FFA726" d="M256 32C192 32 96 165.2 96 288.9 96 412.6 160 480 256 480s160-67.4 160-191.1C416 165.2 320 32 256 32zm-16.1 79.9c-8.9 0-16 7.2-16 16s7.2 16 16 16 16-7.2 16-16-7.1-16-16-16zm32 32c-8.9 0-16 7.2-16 16s7.2 16 16 16 16-7.2 16-16-7.1-16-16-16zM256 288c-44.2 0-80-35.8-80-80s35.8-80 80-80 80 35.8 80 80-35.8 80-80 80z"/></svg>') no-repeat center center;
            background-size: contain;
            cursor: pointer;
            z-index: 100;
            transition: all 0.3s;
        }
        
        .poultry-animation:hover {
            transform: scale(1.1) rotate(10deg);
        }
        
        /* Egg progress */
        .egg-progress {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .egg-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            width: 0;
            transition: width 1s;
        }
        
        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-color);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 90%;
            animation: slideUp 0.3s ease-out;
        }
        
        .toast-success {
            background: var(--success-color);
        }
        
        .toast-error {
            background: var(--danger-color);
        }
        
        .toast-info {
            background: var(--info-color);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 400px) {
            .info-label {
                min-width: 100px;
            }
            
            .qr-image {
                width: 150px;
                height: 150px;
            }
        }
        
        /* Fun animations */
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Poultry theme decorations */
        .poultry-decoration {
            position: absolute;
            opacity: 0.1;
            z-index: 0;
        }
        
        .decoration-1 {
            top: 20%;
            left: 10%;
            font-size: 60px;
            color: var(--primary-color);
        }
        
        .decoration-2 {
            bottom: 15%;
            right: 10%;
            font-size: 50px;
            color: var(--accent-color);
            transform: rotate(30deg);
        }

        /* Growth Tracking Styles */
        .stage-icon-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 2rem;
        }
        
        .stage-icon-large.badge-chick {
            background-color: var(--chick-color);
            box-shadow: 0 4px 15px rgba(255, 209, 102, 0.4);
        }
        
        .stage-icon-large.badge-stag {
            background-color: var(--stag-color);
            box-shadow: 0 4px 15px rgba(6, 214, 160, 0.4);
        }
        
        .stage-icon-large.badge-hen {
            background-color: var(--hen-color);
            box-shadow: 0 4px 15px rgba(255, 154, 139, 0.4);
        }
        
        .stage-icon-large.badge-rooster {
            background-color: var(--rooster-color);
            box-shadow: 0 4px 15px rgba(239, 71, 111, 0.4);
        }
        
        .progress-container {
            width: 100%;
            margin-top: 5px;
        }
        
        .progress-label {
            font-size: 0.7rem;
            color: #6c757d;
            margin-bottom: 2px;
            display: flex;
            justify-content: space-between;
        }
        
        .progress {
            height: 6px;
            border-radius: 3px;
            background-color: #e9ecef;
        }
        
        .progress-bar {
            border-radius: 3px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid white;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Stage Badge Styles */
        .stage-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-chick {
            background-color: var(--chick-color);
            color: #7a5a00;
        }
        
        .badge-stag {
            background-color: var(--stag-color);
            color: #005a47;
        }
        
        .badge-rooster {
            background-color: var(--rooster-color);
            color: white;
        }
        
        .badge-hen {
            background-color: var(--hen-color);
            color: #7a2a1a;
        }
        
        /* Stage animations */
        @keyframes stagePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stage-badge-pulse {
            animation: stagePulse 2s infinite;
        }
        
        .qr-loading {
            filter: blur(2px);
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- App Header -->
        <?php $hideQrSection = isset($_GET['qr']) && $_GET['qr'] == '1'; ?>
        <?php if (!$hideQrSection): ?>
        <div class="app-header position-relative">
            <h1 class="app-title">FeatherTech</h1>
            <p class="app-subtitle">A Hybrid App Poultry Management System</p>
            <div class="poultry-id">ID: <?= htmlspecialchars($animal['id']) ?></div>
            
            <!-- Poultry theme decorations -->
            <i class="fas fa-feather poultry-decoration decoration-1"></i>
            <i class="fas fa-egg poultry-decoration decoration-2"></i>
        </div>
        <?php endif; ?>

        <!-- Animal Gender Badge -->
        <?php if (!$hideQrSection): ?>
        <div class="text-center mt-3 mb-1">
            <?php
            $genderValue = strtolower($animal['gender'] ?? 'male');
            $genderClass = $genderValue === 'male' ? 'male-badge' : ($genderValue === 'female' ? 'female-badge' : '');
            $genderSymbol = $genderValue === 'male' ? '♂' : ($genderValue === 'female' ? '♀' : '');
            ?>
            <span class="badge <?= $genderClass ?> gender-badge">
                <?= $genderSymbol ?> <?= ucfirst($animal['gender'] ?? '') ?>
            </span>
        </div>
        <?php endif; ?>
        <!-- Animal Image Display with enhanced UI/UX and feedback -->
        <?php if (!$hideQrSection): ?>
        <div id="animalImageContainer" class="text-center" style="margin: 0 auto 18px;">
            <?php
            // Show success/error messages for image upload/remove
            if (!empty($_SESSION['success'])) {
                echo '<div class="alert alert-success alert-dismissible fade show w-100 mx-auto" role="alert" style="max-width:350px;">'
                    . htmlspecialchars($_SESSION['success']) .
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                unset($_SESSION['success']);
            }
            if (!empty($_SESSION['error'])) {
                echo '<div class="alert alert-danger alert-dismissible fade show w-100 mx-auto" role="alert" style="max-width:350px;">'
                    . htmlspecialchars($_SESSION['error']) .
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                unset($_SESSION['error']);
            }
            $imagePath = !empty($animal['image']) ? htmlspecialchars($animal['image']) : '';
            $hasImage = $imagePath && file_exists($imagePath);
            ?>
            <div class="animal-image-wrapper position-relative d-inline-block">
                <img src="<?= $hasImage ? $imagePath : 'assets/images/default_animal.png' ?>"
                     alt="<?= $hasImage ? 'Animal Image' : 'No Image' ?>"
                     class="img-fluid rounded shadow animal-profile-img <?= $hasImage ? '' : 'no-animal-img' ?>"
                     style="max-width:220px;max-height:220px;object-fit:cover;">
                <?php if ($hasImage): ?>
                <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2" style="border-radius:50%;" title="Remove Image" data-bs-toggle="modal" data-bs-target="#removeImageModal">
                    <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
                <div class="image-overlay-text <?= $hasImage ? 'd-none' : '' ?>">
                    <i class="fas fa-camera fa-2x mb-2"></i><br>
                    <span>No Gamefowl Chicken image yet</span>
                </div>
            </div>
            <div class="mt-2">
                <!-- Hide Change/Add Image button if accessed via QR code -->
                <?php if (!$hideQrSection): ?>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addImageModal">
                    <i class="fas fa-upload me-1"></i> <?= $hasImage ? 'Change Image' : 'Add Image' ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <!-- Remove Image Modal -->
        <div class="modal fade" id="removeImageModal" tabindex="-1" aria-labelledby="removeImageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="upload_avatar.php" method="POST">
                        <input type="hidden" name="animal_id" value="<?= htmlspecialchars($animal['id']) ?>">
                        <input type="hidden" name="remove_image" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="removeImageModalLabel"><i class="fas fa-trash me-2"></i>Remove Gamefowl Chicken Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                <p>Are you sure you want to remove this Gamefowl Chicken's image? This action cannot be undone.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Remove</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <style>
    .animal-image-wrapper {
        position: relative;
        display: inline-block;
        background: linear-gradient(135deg, #fff8e1 60%, #ffa72622 100%);
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        padding: 10px 10px 0 10px;
        min-width: 180px;
        min-height: 180px;
    }
    .animal-profile-img {
        border-radius: 12px;
        border: 2px solid #ffa72644;
        background: #fff8e1;
        transition: box-shadow 0.2s;
    }
    .animal-profile-img:hover {
        box-shadow: 0 4px 16px #ffa72633;
    }
    .animal-image-wrapper .btn-danger {
        opacity: 0.85;
        transition: opacity 0.2s;
    }
    .animal-image-wrapper .btn-danger:hover {
        opacity: 1;
    }
    .image-overlay-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #bdbdbd;
        text-align: center;
        pointer-events: none;
        font-size: 1.1rem;
        font-weight: 500;
        width: 100%;
    }
    .no-animal-img {
        filter: grayscale(0.2) blur(0.5px) brightness(0.97);
        opacity: 0.7;
    }
    </style>

        <!-- Main Content -->
        <div class="content-section">
        <!-- Add Image Modal -->
        <div class="modal fade" id="addImageModal" tabindex="-1" aria-labelledby="addImageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="uploadImageForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" onsubmit="return validateImageUpload()">
                        <input type="hidden" name="animal_id" value="<?= htmlspecialchars($animal['id']) ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addImageModalLabel"><i class="fas fa-image me-2"></i>Add/Change Gamefowl Chicken Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="uploadAlert" class="alert d-none" role="alert"></div>
                            <div class="mb-3">
                                <label for="animal_image" class="form-label">Select Image (JPG/PNG)</label>
                                <input class="form-control" type="file" id="animal_image" name="animal_image" 
                                       accept="image/jpeg, image/png, image/jpg" required>
                                <div class="form-text">Recommended size: 800x800px</div>
                            </div>
                            <div class="progress d-none" id="uploadProgress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="uploadButton">
                                <span class="spinner-border spinner-border-sm d-none" id="uploadSpinner" role="status" aria-hidden="true"></span>
                                <span id="uploadButtonText">Upload Image</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <style>
    .three-dot-menu .dropdown-toggle::after {
        display: none;
    }
    </style>
            <!-- Basic Information Card -->
            <div class="info-card">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h3>
                <div class="info-row">
                    <span class="info-label">Type:</span>
                    <span class="info-value"><?= htmlspecialchars($animal['type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Breed:</span>
                    <span class="info-value"><?= htmlspecialchars($animal['breed']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age:</span>
                    <span class="info-value">
                        <?= htmlspecialchars($animal['age']) ?> days
                        <?php
                        // Always show the View Care Guide button if the care guide file exists
                        $age = (int)($animal['age'] ?? 0);
                        $type = strtolower($animal['type'] ?? '');
                        $stagePage = '';
                        if ($type === 'chick' && file_exists('chick_care.php')) {
                            $stagePage = 'chick_care.php';
                        } elseif ($type === 'stag' && file_exists('stag_care.php')) {
                            $stagePage = 'stag_care.php';
                        } elseif ($type === 'hen' && file_exists('hen_care.php')) {
                            $stagePage = 'hen_care.php';
                        } elseif ($type === 'rooster' && file_exists('rooster_care.php')) {
                            $stagePage = 'rooster_care.php';
                        }
                        if ($stagePage) {
                            echo ' <a href="' . $stagePage . '?id=' . urlencode($animal['id']) . '" class="btn btn-sm btn-outline-primary ms-2">View Care Guide</a>';
                        }
                        ?>
                    </span>
                </div>
                
                <?php if ($isHen): ?>
                <!-- Egg production progress (only shown for hens) -->
                <div class="egg-progress">
                    <div class="egg-progress-bar" id="eggProgress"></div>
                </div>
                <div class="info-row">
                    <span class="info-label">Egg Production:</span>
                    <span class="info-value"><span id="eggCount">0</span>/12 eggs this week</span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Vaccination History Card -->
            <div class="info-card">
                <h3 class="section-title"><i class="fas fa-history me-2"></i> Vaccination History</h3>
                <?php if (empty($vaccinationHistory)): ?>
                    <p class="text-center text-muted">No vaccination records found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Vaccine</th>
                                    <th>Date</th>
                                    <th>Administered By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vaccinationHistory as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['vaccine_type']) ?></td>
                                        <td><?= date('M j, Y', strtotime($record['vaccination_date'])) ?></td>
                                        <td>User #<?= htmlspecialchars($record['administered_by']) ?></td>
                                        <td><?= htmlspecialchars($record['notes']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Health Information Card -->
            <div class="info-card highlight">
                <h3 class="section-title"><i class="fas fa-heartbeat"></i> Vaccination Record
                    <a href="vaccination_history.php?id=<?= $animal['id'] ?>" class="btn btn-sm btn-outline-info ms-auto" style="float:right;">
                        <i class="fas fa-history"></i> View Vaccination History
                    </a>
                </h3>
                <?php 
                    $lastVaccination = !empty($vaccinationHistory) ? $vaccinationHistory[0] : null;
                ?>
                <div class="info-row">
                    <span class="info-label">Last Vaccination:</span>
                    <span class="info-value"><?= htmlspecialchars($lastVaccination['vaccine_type'] ?? 'None') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value"><?= $lastVaccination ? date('M j, Y', strtotime($lastVaccination['vaccination_date'])) : 'N/A' ?></span>
                </div>
            </div>
            
            <!-- Additional Information Card -->
            <div class="info-card">
                <h3 class="section-title"><i class="fas fa-clipboard-list"></i> Additional Info</h3>
                <div class="info-row">
                    <span class="info-label">Mark:</span>
                    <span class="info-value"><?= htmlspecialchars($animal['mark']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Breed Season:</span>
                    <span class="info-value"><?= htmlspecialchars($animal['breed_season'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Updated:</span>
                    <span class="info-value"><?= date('M j, Y g:i A', strtotime($animal['last_updated'])) ?></span>
                </div>
            </div>
            
       
            <?php
            // Hide QR section and back button if accessed via QR scan (?qr=1 in URL)
            $hideQrSection = isset($_GET['qr']) && $_GET['qr'] == '1';
            ?>
            <?php if (!$hideQrSection): ?>
            <!-- QR Code Section and Back button are hidden when accessed via QR code -->
            <div class="qr-section">
                <h3 class="section-title"><i class="fas fa-qrcode"></i> Gamefowl Chicken QR Code</h3>
                <?php if (!empty($animal['qr_code'])): ?>
                    <?php 
                    // Clean the path by removing query string if exists
                    $cleanPath = strtok($animal['qr_code'], '?');
                    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $cleanPath;
                    if (file_exists($fullPath)): ?>
                        <img id="qrImage" src="<?= htmlspecialchars($cleanPath) ?>?t=<?= $animal['last_modified'] ?>" 
                             alt="QR Code for Poultry ID <?= htmlspecialchars($animal['id']) ?>"
                             class="qr-image floating">
                        <div class="action-buttons">
                            <a href="<?= htmlspecialchars($cleanPath) ?>" download="poultry_<?= htmlspecialchars($animal['id']) ?>_qr.png" class="btn-action btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="includes/qr_generator.php?id=<?= $animal['id'] ?>" class="btn-action btn-secondary" id="regenerateQrBtn">
                                <i class="fas fa-sync-alt"></i> Regenerate
                            </a>
                        </div>
                        <p class="last-updated">Last updated: <?= date('M j, Y g:i A', $animal['last_modified']) ?></p>
                    <?php else: ?>
                        <p style="color: var(--danger-color);">QR Code file missing.</p>
                        <a href="includes/qr_generator.php?id=<?= $animal['id'] ?>" class="btn-action btn-primary">
                            <i class="fas fa-qrcode"></i> Generate QR Code
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: var(--danger-color);">QR Code not generated yet.</p>
                    <a href="includes/qr_generator.php?id=<?= $animal['id'] ?>" class="btn-action btn-primary">
                        <i class="fas fa-qrcode"></i> Generate QR Code
                    </a>
                <?php endif; ?>
            </div>
            <!-- Back button -->
            <a href="dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Poultry animation button -->
        <div class="poultry-animation" id="poultryAnimation" title="Click me!"></div>
        
        <!-- Toast notification container -->
        <div id="toastContainer" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; align-items: center; gap: 10px;"></div>
    </div>

    <script>
    // Function to show toast message
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        
        // Set toast content and classes
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add to container
        toastContainer.insertBefore(toast, toastContainer.firstChild);
        
        // Trigger reflow to enable the transition
        void toast.offsetWidth;
        
        // Add show class to trigger the animation
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 5000);
    }
    
    // Show any session messages
        <?php if (isset($_SESSION['success'])): ?>
            showToast('<?= addslashes($_SESSION['success']) ?>', 'success');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            showToast('<?= addslashes($_SESSION['error']) ?>', 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    });
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize elements
        const regenerateQrBtn = document.getElementById('regenerateQrBtn');
        const qrImage = document.getElementById('qrImage');
        const infoQrImage = document.getElementById('infoQrImage');
        const poultryAnimation = document.getElementById('poultryAnimation');
        const eggProgress = document.getElementById('eggProgress');
        const eggCount = document.getElementById('eggCount');
        const healthStatus = document.getElementById('healthStatus');
        
        
        <?php if ($isHen): ?>
        // Only run egg production animation for hens
        let eggs = 0;
        const eggInterval = setInterval(() => {
            eggs = Math.min(eggs + Math.floor(Math.random() * 2), 12);
            if (eggProgress) eggProgress.style.width = `${(eggs / 12) * 100}%`;
            if (eggCount) eggCount.textContent = eggs;
            
            if (eggs >= 12) {
                clearInterval(eggInterval);
                showToast('This hen has reached maximum egg production!', 'success');
                
                // Random health status changes
                setTimeout(() => {
                    const statuses = ['Healthy', 'Very Healthy', 'Productive', 'Excellent'];
                    const randomStatus = statuses[Math.floor(Math.random() * statuses.length)];
                    if (healthStatus) {
                        healthStatus.textContent = randomStatus;
                        healthStatus.style.color = '#4CAF50';
                    }
                }, 2000);
            }
        }, 1000);
        <?php endif; ?>
        
        // Poultry animation click effect
        poultryAnimation.addEventListener('click', function() {
            // Create floating poultry elements
            for (let i = 0; i < 5; i++) {
                createFloatingPoultry();
            }
            
            // Show fun message
            const messages = [
                "Cluck cluck!",
                "Egg-celent choice!",
                "You're eggs-traordinary!",
                "Feathers looking good!",
                "Poultry power!"
            ];
            const randomMessage = messages[Math.floor(Math.random() * messages.length)];
            showToast(randomMessage, 'info');
        });
        
        // Function to create floating poultry elements
        function createFloatingPoultry() {
            const poultry = document.createElement('div');
            poultry.innerHTML = '<i class="fas fa-feather"></i>';
            poultry.style.position = 'fixed';
            poultry.style.bottom = '20px';
            poultry.style.right = '20px';
            poultry.style.fontSize = '24px';
            poultry.style.color = ['#FFA726', '#5D4037', '#4CAF50', '#2196F3', '#9C27B0'][Math.floor(Math.random() * 5)];
            poultry.style.zIndex = '100';
            poultry.style.cursor = 'pointer';
            poultry.style.transition = 'all 2s ease-out';
            document.body.appendChild(poultry);
            
            // Animate
            setTimeout(() => {
                poultry.style.transform = `translate(${Math.random() * 200 - 100}px, ${-Math.random() * 200 - 100}px) rotate(${Math.random() * 360}deg)`;
                poultry.style.opacity = '0';
            }, 10);
            
            // Remove after animation
            setTimeout(() => {
                poultry.remove();
            }, 2000);
        }
        
        // QR code regeneration
        if (regenerateQrBtn) {
            regenerateQrBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                const btn = this;
                const originalHtml = btn.innerHTML;
                
                try {
                    // Show loading state
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Regenerating...';
                    btn.disabled = true;
                    if (qrImage) qrImage.classList.add('qr-loading');
                    
                    const response = await fetch(btn.href);
                    
                    if (!response.ok) {
                        const error = await response.text();
                        throw new Error(error || 'Failed to regenerate QR code');
                    }
                    
                    // Force page reload with cache busting (fixed path)
                    window.location.href = "view_animal.php?id=<?= $animal['id'] ?>&qr_updated=1&t=" + Date.now();
                    
                } catch (error) {
                    console.error('Error:', error);
                    showToast(`Error: ${error.message}`, 'error');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    if (qrImage) qrImage.classList.remove('qr-loading');
                }
            });
        }
        
        // Info QR image load event
        if (infoQrImage) {
            infoQrImage.addEventListener('load', function() {
                this.classList.remove('qr-refreshing');
                showToast('QR Code refreshed!', 'success');
            });
        }
    });
    
    // Global functions
    function refreshInfoQr() {
        const img = document.getElementById('infoQrImage');
        if (!img) {
            console.error('Info QR image element not found');
            return;
        }
        
        const timestamp = Date.now();
        img.classList.add('qr-refreshing');
        img.src = "includes/generate_info_qr.php?id=<?= $animal['id'] ?>&t=${timestamp}";
    }
    
    function downloadInfoQr() {
        const img = document.getElementById('infoQrImage');
        if (!img) {
            console.error('Info QR image element not found');
            return;
        }
        
        try {
            const link = document.createElement('a');
            link.href = img.src;
            link.download = "poultry_<?= $animal['id'] ?>_info_qr.png";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast('Download started!', 'success');
        } catch (error) {
            console.error('Download failed:', error);
            showToast('Download failed: ' + error.message, 'error');
        }
    }
    
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        toastContainer.appendChild(toast);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Add this to your view_animal.php
document.addEventListener('DOMContentLoaded', function() {
    // Initialize growth chart if chart.js is available
    if (typeof Chart !== 'undefined') {
        const ctx = document.createElement('canvas');
        ctx.id = 'growthChart';
        ctx.height = 300;
        document.querySelector('.growth-section').appendChild(ctx);
        
        const growthData = {
            labels: ['Hatch', '2 Weeks', '4 Weeks', '6 Weeks', '8 Weeks', '12 Weeks', '16 Weeks', '20 Weeks', '24 Weeks'],
            datasets: [{
                label: 'Weight (kg)',
                data: [0.1, 0.3, 0.7, 1.2, 1.8, 2.5, 3.0, 3.5, 3.7],
                borderColor: '#0d44d1',
                backgroundColor: 'rgba(13, 68, 209, 0.1)',
                tension: 0.3,
                fill: true
            }]
        };
        
        new Chart(ctx, {
            type: 'line',
            data: growthData,
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Growth Progression'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Weight: ${context.parsed.y} kg`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Weight (kg)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Age'
                        }
                    }
                }
            }
        });
    }
    
    // Initialize stage tooltips
    const stageTooltips = {
        'chick': 'Chick stage (0-12 weeks): Rapid growth and feather development',
        'stag': 'Stag stage (12-20 weeks): Sexual maturation begins',
        'hen': 'Hen stage (20+ weeks): Egg production begins',
        'rooster': 'Rooster stage (20+ weeks): Fully mature for harvesting'
    };
    
    document.querySelectorAll('.stage-badge').forEach(badge => {
        const stage = badge.classList.contains('badge-chick') ? 'chick' :
                     badge.classList.contains('badge-stag') ? 'stag' :
                     badge.classList.contains('badge-hen') ? 'hen' : 'rooster';
        
        new bootstrap.Tooltip(badge, {
            title: stageTooltips[stage],
            placement: 'top'
        });
    });
    
    // Initialize status tooltips
    const statusTooltips = {
        'Ready for Harvesting': 'Animal has reached optimal size and condition for processing',
        'Ready for Breeding': 'Animal has reached sexual maturity and is ready for reproduction',
        'Ready for Conditioning': 'Animal is ready for special diet/exercise to prepare for next stage',
        'Not Yet Ready': 'Animal is still developing for current stage requirements'
    };
    
    document.querySelectorAll('.status-badge').forEach(badge => {
        const status = badge.textContent.trim();
        if (statusTooltips[status]) {
            new bootstrap.Tooltip(badge, {
                title: statusTooltips[status],
                placement: 'top'
            });
        }
    });
});
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Image upload validation and handling
    function validateImageUpload() {
        const fileInput = document.getElementById('animal_image');
        const file = fileInput.files[0];
        const uploadAlert = document.getElementById('uploadAlert');
        const uploadButton = document.getElementById('uploadButton');
        const uploadSpinner = document.getElementById('uploadSpinner');
        const uploadButtonText = document.getElementById('uploadButtonText');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = uploadProgress.querySelector('.progress-bar');
        
        // Reset UI
        uploadAlert.classList.add('d-none');
        uploadAlert.textContent = '';
        
        // Check if file is selected
        if (!file) {
            showAlert('Please select an image file.', 'danger');
            return false;
        }
        
        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!validTypes.includes(file.type)) {
            showAlert('Invalid file type. Only JPG and PNG are allowed.', 'danger');
            return false;
        }
        
        // Check file size (15MB max)
        const maxSize = 15 * 1024 * 1024; // 15MB
        if (file.size > maxSize) {
            showAlert('File is too large. Maximum size is 15MB.', 'danger');
            return false;
        }
        
        // Show loading state
        uploadButton.disabled = true;
        uploadSpinner.classList.remove('d-none');
        uploadButtonText.textContent = 'Uploading...';
        uploadProgress.classList.remove('d-none');
        
        // Create FormData and send via AJAX
        const formData = new FormData(document.getElementById('uploadImageForm'));
        const xhr = new XMLHttpRequest();
        
        // Upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
            }
        });
        
        // Handle response
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    // Success - redirect to view page which will show success message
                    window.location.href = 'view_animal.php?id=<?= $animal['id'] ?>&upload=success';
                } else {
                    // Error
                    try {
                        const response = JSON.parse(xhr.responseText);
                        showAlert(response.error || 'An error occurred during upload.', 'danger');
                    } catch (e) {
                        showAlert('An error occurred during upload. Please try again.', 'danger');
                    }
                    // Reset UI
                    uploadButton.disabled = false;
                    uploadSpinner.classList.add('d-none');
                    uploadButtonText.textContent = 'Upload Image';
                    progressBar.style.width = '0%';
                    progressBar.setAttribute('aria-valuenow', 0);
                }
            }
        };
        
        // Send request
        xhr.open('POST', 'upload_avatar.php', true);
        xhr.send(formData);
        
        return false; // Prevent default form submission
    }
    
    function showAlert(message, type) {
        const alert = document.getElementById('uploadAlert');
        alert.textContent = message;
        alert.className = `alert alert-${type} d-block`;
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Show success message if redirected after upload
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('upload') && urlParams.get('upload') === 'success') {
            showToast('Image uploaded successfully!', 'success');
            // Remove the upload parameter from URL
            const newUrl = window.location.pathname + '?id=<?= $animal['id'] ?>';
            window.history.replaceState({}, document.title, newUrl);
        }
    });
    </script>
</body>
</html>