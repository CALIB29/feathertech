<?php
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/weather.php';
// Near the top of dashboard.php, after database connection
include 'includes/vaccination_notifications.php';

// Get notifications
$notifications = [];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
try {
    $notifications = getVaccinationNotifications(
        $pdo,
        $_SESSION['user_id'] ?? null,
        $isAdmin
    );
} catch (Exception $e) {
    error_log("Error loading notifications: " . $e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user is logged in or is a secret user
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_secret_user'])) {
    header("Location: index.php");
    exit();
}

// Fetch weather data
$weatherApiKey = '296a750f9c53ec1ea580fbc2ede492dd';
$location = 'Becuran,PH';
try {
    $weatherData = fetchWeather($weatherApiKey, $location);
    $temperature = $weatherData['main']['temp'] ?? null;
    $weatherCondition = $weatherData['weather'][0]['main'] ?? 'Clear';
    $weatherIcon = $weatherData['weather'][0]['icon'] ?? '01d';
} catch (Exception $e) {
    $weatherData = [];
    $temperature = null;
    $weatherCondition = 'Clear';
    $weatherIcon = '01d';
    error_log("Weather API Error: " . $e->getMessage());
}

// Weather alert logic
if (!isset($_SESSION['alert_shown'])) {
    $_SESSION['alert_shown'] = true;

    $isRainy = isset($weatherData['weather'][0]['main']) && strpos($weatherData['weather'][0]['main'], 'Rain') !== false;
    $isSunny = isset($weatherData['weather'][0]['main']) && strpos($weatherData['weather'][0]['main'], 'Clear') !== false;

    // Only show sunny alert in the morning
    if ($isSunny && $currentHour >= 5 && $currentHour < 12) {
        echo "<script>alert('The weather is sunny! Make sure to give water with vitamins to keep the chicks hydrated.');</script>";
    } elseif ($isRainy) {
        echo "<script>alert('It\'s rainy! Provide chicks with antibiotics or vitamins to ensure their health.');</script>";
    }
}

// Fetch animals from the database
try {
    $stmt = $pdo->query("SELECT * FROM animals");
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $animals = [];
    error_log("Database Error: " . $e->getMessage());
}

// Function to format age in months
function formatAge($days)
{
    return floor($days / 30) . " month(s)";
}

// Function to calculate readiness percentage based on type and age
function calculateReadinessPercentage($type, $ageDays)
{
    switch ($type) {
        case 'Chick':
            $targetDays = 180; // 6 months
            break;
        case 'Hen':
            $targetDays = 240; // 8 months
            break;
        case 'Rooster':
            $targetDays = 270; // 9 months
            break;
        default:
            $targetDays = 180; // Default to chick target
    }

    $percentage = min(100, ($ageDays / $targetDays) * 100);
    return round($percentage, 1);
}

// Function to get status details
function getStatusDetails($type, $status)
{
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

    return $statusMap[$status] ?? $statusMap['default'];
}

// Update animal ages daily
try {
    $pdo->query("UPDATE animals SET age = age + 1"); // Increment age by 1 day

    // Update animal statuses based on age and type
    $sql = "
        UPDATE animals
        SET status = CASE
            WHEN type = 'Chick' AND age >= 180 THEN 'Ready for Harvesting'
            WHEN type = 'Hen' AND age >= 240 THEN 'Ready for Breeding'
            WHEN type = 'Rooster' AND age >= 270 THEN 'Ready for Conditioning'
            ELSE status
        END;
    ";
    $pdo->exec($sql);
} catch (PDOException $e) {
    error_log("Error updating animal statuses: " . $e->getMessage());
}

// Replace the existing notification fetch with:
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT vn.*, a.type, a.breed, a.mark, 
               DATEDIFF(vn.due_date, CURDATE()) as days_remaining
        FROM vaccination_notifications vn
        JOIN animals a ON vn.animal_id = a.id
        WHERE vn.status = 'pending'
        AND (vn.user_id = :user_id OR :is_admin = 1)
        ORDER BY 
            CASE WHEN vn.due_date < CURDATE() THEN 0 ELSE 1 END,
            vn.due_date ASC
    ");

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin' ? 1 : 0;
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'] ?? 0,
        ':is_admin' => $isAdmin
    ]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Notification fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FeatherTech Mobile Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d44d1;
            --primary-light: #3d6bdf;
            --secondary-color: #15dfe6;
            --success-color: #4bc986;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #cc0202;
            --card-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            --3d-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.2);
        }

        /* 3D Transformations */
        .card-3d {
            transform-style: preserve-3d;
            transition: all 0.5s ease;
            perspective: 1000px;
        }

        .card-3d:hover {
            transform: translateY(-5px) rotateX(5deg);
            box-shadow: var(--3d-shadow);
        }

        .btn-3d {
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-3d:active {
            transform: translateY(2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Mobile-first base styles */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ed 100%);
            color: #333;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* App Header */
        .app-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .app-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .app-logo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
        }

        /* Main Content */
        .app-container {
            padding: 15px;
        }

        /* Weather Card - 3D Enhanced */
        .weather-card {
            background: linear-gradient(135deg, #4a6cf7 0%, #2541b2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--3d-shadow);
            transform-style: preserve-3d;
            position: relative;
            overflow: hidden;
        }

        .weather-card::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            transform: rotate(30deg);
        }

        .weather-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .weather-icon {
            width: 60px;
            height: 60px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            transform: translateZ(20px);
        }

        /* Notifications Card - 3D Enhanced */
        .notifications-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--3d-shadow);
            margin-bottom: 20px;
            overflow: hidden;
            transform-style: preserve-3d;
            transition: all 0.5s ease;
        }

        .notifications-card:hover {
            transform: translateY(-5px) rotateX(5deg);
        }

        .notifications-header {
            background: linear-gradient(135deg, var(--warning-color) 0%, #ffab00 100%);
            color: #333;
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Animal Grid - Modern Layout */
        .animal-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .animal-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .animal-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--3d-shadow);
        }

        .animal-header {
            background: var(--primary-color);
            color: white;
            padding: 12px 15px;
            font-weight: 500;
        }

        .animal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 15px;
        }

        .animal-detail {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
        }

        /* Enhanced Status Badges */
        .status-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .status-badge i {
            margin-right: 5px;
            font-size: 0.8em;
        }

        .status-success {
            background-color: rgba(75, 201, 134, 0.1);
            color: var(--success-color);
        }

        .status-info {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }

        .status-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .status-secondary {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .status-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        /* Progress Bar Styles */
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

        /* Tooltip Styles */
        .tooltip-inner {
            max-width: 250px;
            padding: 8px 12px;
            text-align: left;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            padding: 0 15px 15px;
        }

        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .view-btn {
            background-color: rgba(13, 68, 209, 0.1);
            color: var(--primary-color);
        }

        .edit-btn {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .archive-btn {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        /* Bottom Navigation - 3D Effect */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -8px 25px -5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            z-index: 1000;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #666;
            text-decoration: none;
            font-size: 0.8rem;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }

        .nav-item.active {
            color: var(--primary-color);
            transform: translateY(-8px);
        }

        .nav-item i {
            font-size: 1.4rem;
            margin-bottom: 3px;
            transition: all 0.3s ease;
        }

        .nav-item.active i {
            transform: scale(1.2);
            text-shadow: 0 4px 8px rgba(13, 68, 209, 0.2);
        }

        /* Sidebar - 3D Effect */
        .offcanvas {
            width: 280px !important;
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
            box-shadow: 8px 0 25px -5px rgba(0, 0, 0, 0.2);
        }

        .sidebar-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px;
            border-top-right-radius: 20px;
        }

        .user-profile {
            text-align: center;
            padding: 20px;
            background: white;
            margin: 15px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transform-style: preserve-3d;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 10px;
            border: 3px solid var(--primary-color);
            object-fit: cover;
            transform: translateZ(20px);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .animal-grid-container {
                grid-template-columns: 1fr;
            }

            .animal-card {
                width: 100%;
            }
        }

        /* Animations */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0) translateZ(0);
            }

            50% {
                transform: translateY(-10px) translateZ(10px);
            }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            object-fit: cover;
        }

        .avatar-container {
            margin: 0 auto;
            width: 80px;
            height: 80px;
        }
    </style>

    <!-- Add Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
</head>

<body>
    <!-- App Header -->
    <div class="app-header">
        <div class="app-title">
            <img src="/assets/images/FeatherTech.jpg" alt="Logo" class="app-logo">
            FeatherTech
        </div>
        <button class="btn btn-sm btn-light" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Main Content -->
    <div class="app-container">
        <!-- Weather Card -->
        <div class="weather-card card-3d">
            <div class="weather-content">
                <div>
                    <h5>Weather Update</h5>
                    <p class="mb-0"><?= $weatherData['weather'][0]['main'] ?? 'N/A' ?> - <?= $temperature ? $temperature . "°C" : "N/A" ?></p>
                </div>
                <img src="https://openweathermap.org/img/wn/<?= $weatherIcon ?>@2x.png" alt="Weather Icon" class="weather-icon floating">
            </div>
        </div>

        <!-- Notifications Card -->
        <div class="notifications-card card-3d">
            <div class="notifications-header">
                <div>
                    <i class="fas fa-bell"></i> Vaccination Tasks
                    <span class="badge bg-danger ms-2"><?= count($notifications) ?></span>
                </div>
                <button class="btn btn-sm btn-light btn-3d" id="refreshNotificationsBtn">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <p>No pending vaccination tasks</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item mb-3" data-id="<?= $notification['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($notification['type']) ?> #<?= $notification['animal_id'] ?></strong>
                                    <?php if ($notification['days_remaining'] < 0): ?>
                                        <span class="badge bg-danger ms-2">OVERDUE</span>
                                    <?php elseif ($notification['days_remaining'] <= 3): ?>
                                        <span class="badge bg-warning text-dark ms-2">DUE SOON</span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm btn-primary btn-3d complete-notification-btn">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                            <div class="text-muted small mb-2">
                                <i class="fas fa-calendar-day"></i>
                                <?= date('M j, Y', strtotime($notification['due_date'])) ?>
                                (<?= $notification['days_remaining'] < 0 ?
                                        abs($notification['days_remaining']) . ' days overdue' :
                                        $notification['days_remaining'] . ' days remaining' ?>)
                            </div>
                            <div>
                                <span class="badge bg-light text-dark me-1">
                                    <i class="fas fa-syringe"></i>
                                    <?= htmlspecialchars($notification['vaccination_type']) ?>
                                </span>
                                <?php if (!empty($notification['mark'])): ?>
                                    <span class="badge bg-info text-dark">
                                        <i class="fas fa-tag"></i> <?= htmlspecialchars($notification['mark']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Animal Grid -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Animal Records</h5>
            <a href="add_animal.php" class="btn btn-sm btn-primary btn-3d">
                <i class="fas fa-plus"></i> Add Animal
            </a>
        </div>

        <?php if (empty($animals)): ?>
            <div class="text-center py-5">
                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                <h5>No Animals Found</h5>
                <p>Get started by adding your first animal</p>
                <a href="add_animal.php" class="btn btn-primary btn-3d mt-2">
                    <i class="fas fa-plus"></i> Add Animal
                </a>
            </div>
        <?php else: ?>
            <div class="animal-grid-container">
                <?php foreach ($animals as $animal):
                    $readinessPercentage = calculateReadinessPercentage($animal['type'], $animal['age']);
                    $statusDetails = getStatusDetails($animal['type'], $animal['status']);
                ?>
                    <div class="animal-card card-3d">
                        <div class="animal-header">
                            <?= htmlspecialchars($animal['type']) ?> #<?= $animal['id'] ?>
                        </div>
                        <div class="animal-grid">
                            <div class="animal-detail">
                                <span class="detail-label">Age</span>
                                <span class="detail-value"><?= formatAge($animal['age']) ?></span>
                            </div>
                            <div class="animal-detail">
                                <span class="detail-label">Breed</span>
                                <span class="detail-value"><?= htmlspecialchars($animal['breed']) ?></span>
                            </div>
                            <div class="animal-detail">
                                <span class="detail-label">Mark</span>
                                <span class="detail-value"><?= htmlspecialchars($animal['mark']) ?></span>
                            </div>
                            <div class="animal-detail">
                                <span class="detail-label">Status</span>
                                <div class="status-container">
                                    <span class="status-badge status-<?= $statusDetails['color'] ?>"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="<?= $statusDetails['description'] ?>">
                                        <i class="fas <?= $statusDetails['icon'] ?>"></i>
                                        <?= htmlspecialchars($animal['status']) ?>
                                    </span>
                                    <div class="progress-container">
                                        <div class="progress-label">
                                            <span>Development</span>
                                            <span><?= $readinessPercentage ?>%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?= $statusDetails['color'] ?>"
                                                role="progressbar"
                                                style="width: <?= $readinessPercentage ?>%"
                                                aria-valuenow="<?= $readinessPercentage ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="view_animal.php?id=<?= $animal['id'] ?>" class="action-btn view-btn btn-3d">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="update_animal.php?id=<?= $animal['id'] ?>" class="action-btn edit-btn btn-3d">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="action-btn archive-btn btn-3d"
                                data-id="<?= $animal['id'] ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#archiveModal">
                                <i class="fas fa-archive"></i> Archive
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item active">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="add_animal.php" class="nav-item">
            <i class="fas fa-plus"></i>
            <span>Add</span>
        </a>
        <a href="scan_qr.php" class="nav-item">
            <i class="fas fa-qrcode"></i>
            <span>Scan</span>
        </a>
        <a href="archive.php" class="nav-item">
            <i class="fas fa-archive"></i>
            <span>Archive</span>
        </a>
        <a href="ai_chicken_health.php" class="nav-item">
            <i class="fas fa-camera"></i>
            <span>Health Scan</span>
        </a>

    </div>

    <!-- Sidebar Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <img src="/assets/images/FeatherTech.jpg" alt="Logo" class="app-logo me-2">
                <h5 class="offcanvas-title mb-0">Menu</h5>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="user-profile card-3d">
            <div class="text-center">
                <div class="avatar-container">
                    <img src="<?= !empty($_SESSION['avatar_path']) ? htmlspecialchars($_SESSION['avatar_path']) : 'assets/images/solo_leveling.jpeg' ?>"
                        class="user-avatar"
                        onerror="this.src='assets/images/solo_leveling.jpeg'"
                        alt="Profile Avatar">
                </div>
                <h6 class="mb-1 mt-2"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></h6>
                <small class="text-muted"><?= ucfirst($_SESSION['role'] ?? 'User') ?></small>
            </div>
        </div>

        <div class="list-group list-group-flush">
            <a href="dashboard.php" class="list-group-item list-group-item-action">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <a href="add_animal.php" class="list-group-item list-group-item-action">
                <i class="fas fa-plus me-2"></i> Add Animal
            </a>
            <a href="register.php" class="list-group-item list-group-item-action">
                <i class="fas fa-user-plus me-2"></i> Add User
            </a>
            <a href="add_egg_production.php" class="list-group-item list-group-item-action">
                <i class="fas fa-egg me-2"></i> Egg Production
            </a>
            <a href="add_mortality_record.php" class="list-group-item list-group-item-action">
                <i class="fas fa-book-medical me-2"></i> Mortality Record
            </a>
            <a href="scan_qr.php" class="list-group-item list-group-item-action">
                <i class="fas fa-qrcode me-2"></i> Scan QR Code
            </a>
            <a href="archive.php" class="list-group-item list-group-item-action">
                <i class="fas fa-archive me-2"></i> Archived Records
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action">
                <i class="fas fa-cog me-2"></i> Settings
            </a>
            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Archive Modal -->
    <div class="modal fade" id="archiveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this record? It will be moved to the archive history.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmArchive">Archive Record</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Completion Modal -->
    <div class="modal fade" id="completeNotificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Complete Vaccination Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to mark this vaccination as completed?</p>
                    <form id="completeNotificationForm">
                        <input type="hidden" id="notificationId">
                        <div class="mb-3">
                            <label for="completionNotes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="completionNotes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmCompleteNotification">Confirm Completion</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Archive modal handling
            document.querySelectorAll('.archive-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    document.getElementById('confirmArchive').href = `delete_animal.php?id=${id}`;
                });
            });

            // Notification completion handling
            document.querySelectorAll('.complete-notification-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const notificationId = this.closest('.notification-item').dataset.id;
                    document.getElementById('notificationId').value = notificationId;
                    var modal = new bootstrap.Modal(document.getElementById('completeNotificationModal'));
                    modal.show();
                });
            });

            // Refresh notifications
            document.getElementById('refreshNotificationsBtn').addEventListener('click', function() {
                const notificationsCard = document.querySelector('.notifications-card');
                notificationsCard.classList.add('refreshing');
                this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';

                fetch('fetch_vaccination_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update notification count
                        document.querySelector('.notifications-header .badge').textContent = data.length;

                        // Update notifications list
                        const notificationsList = document.querySelector('.notifications-card .card-body');

                        if (data.length === 0) {
                            notificationsList.innerHTML = `
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <p>No pending vaccination tasks</p>
                                </div>
                            `;
                        } else {
                            let html = '';
                            data.forEach(notification => {
                                const dueDate = new Date(notification.due_date);
                                const formattedDate = dueDate.toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric'
                                });

                                html += `
                                    <div class="notification-item mb-3" data-id="${notification.id}">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong>${notification.type} #${notification.animal_id}</strong>
                                                ${notification.days_remaining < 0 ? 
                                                    '<span class="badge bg-danger ms-2">OVERDUE</span>' : 
                                                    (notification.days_remaining <= 3 ? 
                                                    '<span class="badge bg-warning text-dark ms-2">DUE SOON</span>' : '')}
                                            </div>
                                            <button class="btn btn-sm btn-primary btn-3d complete-notification-btn">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted small mb-2">
                                            <i class="fas fa-calendar-day"></i>
                                            ${formattedDate}
                                            (${notification.days_remaining < 0 ? 
                                                Math.abs(notification.days_remaining) + ' days overdue' : 
                                                notification.days_remaining + ' days remaining'})
                                        </div>
                                        <div>
                                            <span class="badge bg-light text-dark me-1">
                                                <i class="fas fa-syringe"></i> 
                                                ${notification.vaccination_type}
                                            </span>
                                            ${notification.mark ? 
                                                `<span class="badge bg-info text-dark">
                                                    <i class="fas fa-tag"></i> ${notification.mark}
                                                </span>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                            notificationsList.innerHTML = html;

                            // Reattach event listeners to new buttons
                            document.querySelectorAll('.complete-notification-btn').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const notificationId = this.closest('.notification-item').dataset.id;
                                    document.getElementById('notificationId').value = notificationId;
                                    var modal = new bootstrap.Modal(document.getElementById('completeNotificationModal'));
                                    modal.show();
                                });
                            });
                        }
                    })
                    .finally(() => {
                        notificationsCard.classList.remove('refreshing');
                        this.innerHTML = '<i class="fas fa-sync-alt"></i>';
                    });
            });

            // Confirm notification completion
            document.getElementById('confirmCompleteNotification').addEventListener('click', function() {
                const notificationId = document.getElementById('notificationId').value;
                const notes = document.getElementById('completionNotes').value;

                fetch('complete_vaccination.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            notificationId: notificationId,
                            notes: notes,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the completed notification from the list
                            document.querySelector(`.notification-item[data-id="${notificationId}"]`).remove();

                            // Update notification count
                            const notificationCount = document.querySelectorAll('.notification-item').length;
                            document.querySelector('.notifications-header .badge').textContent = notificationCount;

                            // Show empty state if no more notifications
                            if (notificationCount === 0) {
                                const notificationsList = document.querySelector('.notifications-card .card-body');
                                notificationsList.innerHTML = `
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <p>No pending vaccination tasks</p>
                                </div>
                            `;
                            }

                            // Close the modal
                            bootstrap.Modal.getInstance(document.getElementById('completeNotificationModal')).hide();

                            // Show success toast
                            showToast('Vaccination completed successfully!', 'success');
                        } else {
                            showToast('Error completing vaccination: ' + (data.message || 'Unknown error'), 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Network error: ' + error.message, 'danger');
                    });
            });
        });

        // Helper function to show toast notifications
        function showToast(message, type) {
            const toastContainer = document.createElement('div');
            toastContainer.className = `toast-container position-fixed bottom-0 end-0 p-3`;
            document.body.appendChild(toastContainer);

            const toastEl = document.createElement('div');
            toastEl.className = `toast show align-items-center text-white bg-${type} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');

            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;

            toastContainer.appendChild(toastEl);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                toastEl.classList.remove('show');
                setTimeout(() => {
                    toastContainer.remove();
                }, 300);
            }, 5000);
        }
    </script>
</body>

</html>