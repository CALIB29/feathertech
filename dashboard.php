<?php
// Start session at the VERY BEGINNING of the script
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include files after session start
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/weather.php';
require_once 'includes/vaccination_notifications.php';
require_once 'includes/db_schema.php'; 

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user is logged in or is a secret user
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_secret_user'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables with default values
$weatherData = [];
$temperature = null;
$weatherCondition = 'Clear';
$weatherIcon = '01d';
$animals = [];
$notifications = [];
$currentHour = date('G'); // Current hour for weather alerts

// Database schema setup for vaccination system
try {
    setupVaccinationSchema($pdo);
} catch (Exception $e) {
    error_log("Schema setup error: " . $e->getMessage());
}

// Get notifications
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

// Fetch weather data
$weatherApiKey = '296a750f9c53ec1ea580fbc2ede492dd';
$location = 'Becuran,PH';
try {
    $weatherData = fetchWeather($weatherApiKey, $location);
    $temperature = $weatherData['main']['temp'] ?? null;
    $weatherCondition = $weatherData['weather'][0]['main'] ?? 'Clear';
    $weatherIcon = $weatherData['weather'][0]['icon'] ?? '01d';
} catch (Exception $e) {
    error_log("Weather API Error: " . $e->getMessage());
}

// Weather alert logic
if (!isset($_SESSION['alert_shown'])) {
    $_SESSION['alert_shown'] = true;

    $isRainy = isset($weatherData['weather'][0]['main']) && 
               stripos($weatherData['weather'][0]['main'], 'Rain') !== false;
    $isSunny = isset($weatherData['weather'][0]['main']) && 
               stripos($weatherData['weather'][0]['main'], 'Clear') !== false;

    // Only show sunny alert in the morning
    if ($isSunny && $currentHour >= 5 && $currentHour < 12) {
        echo "<script>alert('The weather is sunny! Make sure to give water with vitamins to keep the chicks hydrated.');</script>";
    } elseif ($isRainy) {
        echo "<script>alert('It\\'s rainy! Provide chicks with antibiotics or vitamins to ensure their health.');</script>";
    }
}

// Fetch animals grouped by type from the database
try {
    // First, get all distinct animal types
    $typeStmt = $pdo->query("SELECT DISTINCT type FROM animals ORDER BY type");
    $animalTypes = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Then get all animals with their data
    $animalsByType = [];
    foreach ($animalTypes as $type) {
        $stmt = $pdo->prepare("SELECT * FROM animals WHERE type = ? ORDER BY id");
        $stmt->execute([$type]);
        $animalsByType[$type] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For backward compatibility, maintain the original $animals array
    $animals = [];
    foreach ($animalsByType as $typeAnimals) {
        $animals = array_merge($animals, $typeAnimals);
    }
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $animals = [];
    $animalsByType = [];
}

// Function to format age in months and days
function formatAge($days) {
    $months = floor($days / 30);
    $remainingDays = $days % 30;
    return $months . " month(s) " . $remainingDays . " day(s)";
}

// Function to get accurate age from either age field or hatch date
function getAccurateAge($animal) {
    // If we have a hatch date, calculate age from that (most accurate)
    if (!empty($animal['hatch_date'])) {
        try {
            $hatchDate = new DateTime($animal['hatch_date']);
            $currentDate = new DateTime();
            $ageInterval = $hatchDate->diff($currentDate);
            return $ageInterval->days;
        } catch (Exception $e) {
            error_log("Error calculating age from hatch date: " . $e->getMessage());
        }
    }
    // Fall back to age field if no hatch date or calculation failed
    return $animal['age'] ?? 0;
}

// Expert-level readiness calculation for poultry growth stages
function calculateReadinessPercentage($animal) {
    $type = strtolower($animal['type'] ?? '');
    $ageDays = (int)($animal['age'] ?? 0);
    $gender = strtolower($animal['gender'] ?? 'male');
    $status = $animal['status'] ?? '';

    // If already in a "ready" status, show 100%
    if (strpos($status, 'Ready for') === 0) {
        return 100.0;
    }

    if ($type === 'chick') {
        if ($gender === 'male') {
            $target = 150; // Stag at 150 days
            $min = 0;
        } else {
            $target = 210; // Hen at 210 days
            $min = 0;
        }
        $progress = ($ageDays - $min) / ($target - $min);
        $progress = max(0, min($progress, 1));
        return round($progress * 100, 1);
    }
    if ($type === 'stag') {
        // Stag: 150-269d, ready for conditioning at 270d
        $min = 150;
        $target = 270;
        $progress = ($ageDays - $min) / ($target - $min);
        $progress = max(0, min($progress, 1));
        return round($progress * 100, 1);
    }
    if ($type === 'rooster') {
        // Rooster: 270+d, readiness is always 100%
        return 100.0;
    }
    if ($type === 'hen') {
        // Hen: 210+d, readiness is always 100%
        return 100.0;
    }
    // Fallback for unknown types
    return 0.0;
}

// Function to get status details
function getStatusDetails($status) {
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

// Update animal ages and statuses daily
try {
    // Increment age by 1 day
    $pdo->query("UPDATE animals SET age = COALESCE(age, 0) + 1");
    
    // Update animal types and statuses based on age and gender
    $updateSql = "
    UPDATE animals
    SET 
        type = CASE
            WHEN gender = 'male' AND type = 'Chick' AND age >= 150 THEN 'Stag'
            WHEN gender = 'male' AND type = 'Stag' AND age >= 270 THEN 'Rooster'
            WHEN gender = 'female' AND type = 'Chick' AND age >= 210 THEN 'Hen'
            ELSE type
        END,
        status = CASE
            WHEN gender = 'male' AND type = 'Chick' AND age >= 150 THEN 'Ready for Harvesting'
            WHEN gender = 'male' AND type = 'Stag' AND age >= 270 THEN 'Ready for Conditioning'
            WHEN gender = 'female' AND type = 'Hen' AND age >= 210 THEN 'Ready for Breeding'
            ELSE status
        END
    ";
    $pdo->exec($updateSql);
} catch (PDOException $e) {
    error_log("Error updating animal statuses: " . $e->getMessage());
}

// Get notifications
try {
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin' ? 1 : 0;
    $stmt = $pdo->prepare("
        SELECT vn.*, a.type, a.breed, a.mark, 
               DATEDIFF(vn.due_date, CURDATE()) as days_remaining,
               vs.administration_method, vs.notes as schedule_notes
        FROM vaccination_notifications vn
        JOIN animals a ON vn.animal_id = a.id
        JOIN vaccination_schedules vs ON vn.schedule_id = vs.id
        WHERE vn.status = 'pending'
        AND (a.user_id = :user_id OR :is_admin = 1)
        ORDER BY 
            CASE WHEN vn.due_date < CURDATE() THEN 0 ELSE 1 END,
            vn.due_date ASC
    ");
    
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
    <title>FeatherTech Dashboard</title>
    <link href="assets/images/6.0.png" rel="icon">
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" 
          crossorigin="anonymous">
    <!-- Font Awesome 6.4.2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" 
          integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts with display=swap and preconnect hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Modern Color Palette */
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --primary-lighter: #4cc9f0;
            --primary-dark: #3a0ca3;
            --secondary-color: #7209b7;
            --accent-color: #f72585;
            --success-color: #4cc9a0;
            --info-color: #4895ef;
            --warning-color: #f4a261;
            --danger-color: #ef476f;
            --light-color: #f8f9fa;
            --light-gray: #e9ecef;
            --medium-gray: #adb5bd;
            --dark-color: #212529;
            
            /* Poultry Theme Colors */
            --poultry-yellow: #ffd166;
            --poultry-orange: #ff9e00;
            --poultry-red: #ef476f;
            --poultry-brown: #6d4c41;
            --poultry-white: #f8f9fa;
            --poultry-light: #fff8e1;
            
            /* Card & Shadows */
            --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --card-hover-shadow: 0 8px 30px rgba(0,0,0,0.12);
            
            /* Border Radius */
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            
            /* Transitions */
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
            --card-hover-shadow: 0 8px 24px rgba(0,0,0,0.15);
            --neumorphic-shadow: 8px 8px 16px #d9d9d9, -8px -8px 16px #ffffff;
        }
        
        /* Base Styles */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
            padding-bottom: 80px;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNDgsMTk3LDU1LDAuMDUpIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3BhdHRlcm4pIi8+PC9zdmc+');
        }
        
        /* App Header - Poultry Tech Theme */
        .app-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--poultry-yellow);
        }
        
        .app-title {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            letter-spacing: 0.5px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .app-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            border: 2px solid var(--poultry-yellow);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .app-logo:hover {
            transform: rotate(15deg) scale(1.1);
        }
        
        /* Main Content Area */
        .app-container {
            padding: 20px;
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Weather Card - Animated */
        .weather-card {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1;
        }
        
        .weather-card::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
            z-index: -1;
            animation: shimmer 8s infinite linear;
        }
        
        .weather-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .weather-icon {
            width: 70px;
            height: 70px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            animation: float 4s ease-in-out infinite;
        }
        
        /* Notifications Dropdown - Redesigned */
        .notification-dropdown {
            width: 350px !important;
            max-height: 70vh;
            overflow-y: auto;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 0;
            transform: translateY(10px) !important;
        }
        
        .notification-dropdown .dropdown-header {
            background: linear-gradient(135deg, var(--poultry-orange) 0%, var(--poultry-red) 100%);
            color: white;
            padding: 12px 15px;
            font-weight: 600;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-dropdown .dropdown-item {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            white-space: normal;
            transition: all 0.2s;
            color: #333;
        }
        
        .notification-dropdown .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .notification-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
            padding-left: 20px;
        }
        
        .notification-dropdown .dropdown-item.unread {
            background-color: #f8f9fa;
            border-left: 3px solid var(--poultry-orange);
        }
        
        .notification-dropdown .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
            display: block;
            margin-top: 4px;
        }
        
        .notification-dropdown .notification-message {
            display: block;
            margin-bottom: 4px;
        }
        
        .notification-dropdown .dropdown-footer {
            padding: 10px 15px;
            text-align: center;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 12px 12px;
        }
        
        .notification-dropdown .dropdown-footer a {
            color: var(--poultry-orange);
            font-weight: 500;
        }
        
        .notification-dropdown .empty-notifications {
            padding: 20px 15px;
            text-align: center;
            color: #6c757d;
        }
        
        /* Animal Cards - Poultry Theme */
        /* Responsive Layout */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-container {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .animal-grid-container {
                grid-template-columns: 1fr;
                padding: 10px;
            }
            
            .animal-card {
                margin-bottom: 15px;
            }
            
            .category-header h4 {
                font-size: 1.1rem;
            }
            
            .status-badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .animal-grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1025px) {
            .animal-grid-container {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }
        
        .animal-categories {
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .animal-category {
            background: #fff;
            border-radius: var(--border-radius-md);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: transform var(--transition-normal), box-shadow var(--transition-normal);
        }
        
        .animal-category:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .category-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            cursor: pointer;
            padding: 1rem 1.5rem;
            transition: all var(--transition-normal);
            border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0;
        }
        
        .category-header:hover {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
        }
        
        .category-header[aria-expanded="false"] .fa-chevron-down {
            transform: rotate(-90deg);
        }
        
        .category-header .fa-chevron-down {
            transition: transform 0.3s ease;
        }
        
        .animal-grid-container {
            display: grid;
            gap: 1.5rem;
            padding: 1.5rem;
            background-color: var(--poultry-white);
            border-radius: 0 0 var(--border-radius-md) var(--border-radius-md);
        }
        
        .animal-card {
            background: white;
            border-radius: var(--border-radius-md);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all var(--transition-normal);
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
        }
        
        .animal-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .animal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 18px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .animal-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .animal-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .animal-detail {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        /* Status Badges - Poultry Colors */
        .badge-chick {
            background-color: #fff8e1;
            color: #ffb300;
            border: 1px solid #ffe082;
        }

        .badge-stag {
            background-color: #ffebee;
            color: #e53935;
            border: 1px solid #ef9a9a;
        }

        .badge-rooster {
            background-color: #e3f2fd;
            color: #1e88e5;
            border: 1px solid #90caf9;
        }

        .badge-hen {
            background-color: #e8f5e9;
            color: #43a047;
            border: 1px solid #a5d6a7;
        }

        .gender-badge {
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
        }

        .male-badge {
            background-color: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        .female-badge {
            background-color: #fce4ec;
            color: #ad1457;
            border: 1px solid #f8bbd0;
        }
        
        /* Progress Bars - Animated */
        .progress-container {
            width: 100%;
            margin-top: 8px;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-bar {
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }
        
        /* Action Buttons - Interactive */
        .action-buttons {
            display: flex;
            gap: 10px;
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
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .action-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .view-btn {
            background-color: var(--primary-color);
            color: white;
        }
        
        .archive-btn {
            background-color: var(--poultry-red);
            color: white;
        }
        
        /* Bottom Navigation - Poultry Theme */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            z-index: 1000;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            border-top: 3px solid var(--poultry-yellow);
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
            position: relative;
        }
        
        .nav-item.active {
            color: var(--primary-color);
            transform: translateY(-8px);
        }
        
        .nav-item.active::after {
            content: "";
            position: absolute;
            bottom: -12px;
            width: 6px;
            height: 6px;
            background-color: var(--poultry-yellow);
            border-radius: 50%;
        }
        
        .nav-item i {
            font-size: 1.4rem;
            margin-bottom: 3px;
            transition: all 0.3s ease;
        }
        
        .nav-item.active i {
            color: var(--poultry-yellow);
            transform: scale(1.2);
            text-shadow: 0 2px 4px rgba(248, 197, 55, 0.3);
        }
        
                /* Sidebar - Poultry Theme */
        .sidebar-modern {
            width: 300px !important;
            border-top-right-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 10px 0 32px -8px rgba(0,0,0,0.18);
            background: linear-gradient(135deg, #fff 60%, var(--primary-light) 100%);
            border: none;
        }
        .sidebar-header-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: #fff;
            border-top-right-radius: 24px;
            border-bottom: 3px solid var(--poultry-yellow);
            min-height: 60px;
        }
        .sidebar-title {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .sidebar-user-section {
            background: linear-gradient(135deg, #fffbe7 60%, #fff 100%);
            border-radius: 16px;
            margin: 18px 18px 10px 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .sidebar-avatar-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }
        .sidebar-user-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            transition: all 0.3s;
        }
        .sidebar-user-avatar:hover {
            transform: scale(1.07);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .sidebar-user-name {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .sidebar-user-role {
            font-size: 0.9rem;
        }
        .sidebar-section-label {
            letter-spacing: 1px;
            margin-top: 10px;
            margin-bottom: 2px;
        }
        .sidebar-list-group .sidebar-link {
            display: flex;
            align-items: center;
            font-size: 1rem;
            padding: 14px 22px;
            border: none;
            background: transparent;
            transition: background 0.18s, color 0.18s;
        }
        .sidebar-list-group .sidebar-link .sidebar-icon {
            font-size: 1.25rem;
            margin-right: 14px;
            min-width: 22px;
            text-align: center;
        }
        .sidebar-list-group .sidebar-link:hover, .sidebar-list-group .sidebar-link:focus {
            background: linear-gradient(90deg, var(--primary-light) 0%, #fff 100%);
            color: var(--primary-color);
        }
        .sidebar-list-group .sidebar-link.text-danger:hover {
            color: #fff;
            background: linear-gradient(90deg, #e53935 0%, #ffb300 100%);
        }
        .sidebar-list-group .sidebar-link.active {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: #fff;
        }
        
        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0) translateZ(0); }
            50% { transform: translateY(-8px) translateZ(0); }
        }
        
        @keyframes shimmer {
            0% { transform: rotate(30deg) translate(-10%, -10%); }
            100% { transform: rotate(30deg) translate(10%, 10%); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .animal-grid-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .animal-details {
                grid-template-columns: 1fr 1fr;
            }
            
            .app-container {
                padding: 15px;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- App Header with Poultry Tech Theme -->
    <div class="app-header">
        <div class="app-title">
            <img src="/assets/images/FeatherTech.jpg" alt="Logo" class="app-logo pulse">
            <span>Feather<span style="color: var(--poultry-yellow);">Tech</span></span>
        </div>
        <div>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Notification Dropdown for Admin - Redesigned -->
            <div class="dropdown d-inline-block">
                <button class="btn btn-sm btn-light position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background: white; border: 1px solid #e9ecef; border-radius: 8px; padding: 8px 12px;">
                    <i class="fas fa-bell" style="color: #495057;"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-count" style="font-size: 0.6rem; padding: 3px 6px; min-width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">
                        0
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>Notifications</span>
                        <a href="#" class="text-white mark-all-read" style="font-size: 0.8rem;">Mark all as read</a>
                    </li>
                    <div class="notifications-container" style="max-height: 400px; overflow-y: auto;">
                        <li><a class="dropdown-item text-center text-muted empty-notifications" href="#">No new notifications</a></li>
                    </div>
                    <li class="dropdown-footer">
                        <a href="admin_notifications.php">View all notifications</a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <button class="btn btn-sm btn-light ms-2" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="app-container">
        <!-- Weather Card with Animation -->
        <div class="weather-card">
            <div class="weather-content">
                <div>
                    <h5><i class="fas fa-cloud-sun me-2"></i> Weather Update</h5>
                    <p class="mb-0"><?= htmlspecialchars($weatherCondition) ?> - <?= $temperature ? round($temperature) . "°C" : "N/A" ?></p>
                </div>
                <?php if ($weatherIcon): ?>
                <a href="weather_details.php" class="weather-icon-link">
                    <img src="https://openweathermap.org/img/wn/<?= htmlspecialchars($weatherIcon) ?>@2x.png" 
                         alt="Weather Icon" class="weather-icon">
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Poultry Showcase Carousel -->
        <div id="poultryCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
            <div class="carousel-inner rounded-3 shadow-sm">
                <div class="carousel-item active">
                    <img src="assets/images/rooster1.jfif" class="d-block w-100" alt="Rooster 1">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Premium Poultry</h5>
                        <p>Healthy and well-cared for gamefowl rooster</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="assets/images/hen1.jfif" class="d-block w-100" alt="Hen 1">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Happy Hens</h5>
                        <p>Producing quality eggs daily</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="assets/images/chick2.jfif" class="d-block w-100" alt="Hen 1">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Healthy Stags</h5>
                        <p>Healthy Stags growing</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="assets/images/chick1.jfif" class="d-block w-100" alt="Chicks">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>New Arrivals</h5>
                        <p>Healthy chicks growing strong</p>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#poultryCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#poultryCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>

        <style>
            .weather-icon-link {
                display: inline-block;
                transition: transform 0.3s ease;
            }
            .weather-icon-link:hover {
                transform: scale(1.1);
            }
            .weather-icon {
                cursor: pointer;
            }
            
            .carousel {
                margin: 20px 0;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .carousel-item {
                height: 250px;
                background: #f8f9fa;
                transition: transform 0.6s ease-in-out, opacity 0.6s ease-in-out;
            }
            .carousel-item img {
                object-fit: cover;
                height: 100%;
                width: 100%;
            }
            .carousel-caption {
                background: rgba(0, 0, 0, 0.5);
                border-radius: 10px;
                padding: 15px;
                bottom: 30px;
                left: 50%;
                right: auto;
                transform: translateX(-50%);
                max-width: 80%;
            }
            .carousel-caption h5 {
                font-size: 1.25rem;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .carousel-caption p {
                font-size: 0.9rem;
                margin-bottom: 0;
            }
            .carousel-control-prev,
            .carousel-control-next {
                width: 5%;
                opacity: 0.8;
                transition: opacity 0.3s ease;
            }
            .carousel-control-prev:hover,
            .carousel-control-next:hover {
                opacity: 1;
            }
            @media (max-width: 768px) {
                .carousel-item {
                    height: 200px;
                }
                .carousel-caption {
                    padding: 10px;
                    bottom: 15px;
                }
                .carousel-caption h5 {
                    font-size: 1rem;
                }
                .carousel-caption p {
                    font-size: 0.8rem;
                }
            }
        </style>
        
               
        
        <!-- Animal Records Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-kiwi-bird me-2" style="color: var(--poultry-orange);"></i> Poultry Records</h5>
            <a href="add_animal.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i> Add Poultry
            </a>
        </div>

        <?php if (empty($animals)): ?>
            <div class="text-center py-5">
                <i class="fas fa-database fa-3x mb-3" style="color: var(--poultry-yellow);"></i>
                <h5>No Poultry Records Found</h5>
                <p>Get started by adding your first poultry record</p>
                <a href="add_animal.php" class="btn btn-primary mt-2">
                    <i class="fas fa-plus me-1"></i> Add Poultry
                </a>
            </div>
        <?php else: ?>
            <div class="animal-categories">
                <?php foreach ($animalsByType as $type => $animalsOfType): 
                    // Skip empty categories
                    if (empty($animalsOfType)) continue;
                    
                    // Get a unique ID for the collapse element
                    $collapseId = 'collapse-' . preg_replace('/[^a-z0-9]/', '-', strtolower($type));
                ?>
                <div class="animal-category mb-4">
                    <div class="category-header d-flex justify-content-between align-items-center p-2 mb-2 rounded" 
                         data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" 
                         aria-expanded="true" aria-controls="<?= $collapseId ?>">
                        <h4 class="mb-0">
                            <i class="fas fa-chevron-down me-2"></i>
                            <?= htmlspecialchars(ucfirst($type)) ?> 
                            <span class="badge bg-primary ms-2"><?= count($animalsOfType) ?></span>
                        </h4>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-light text-dark me-2">
                                <?= count(array_filter($animalsOfType, fn($a) => ($a['status'] ?? '') === 'Breeding')) ?> Breeding
                            </span>
                            <span class="badge bg-light text-dark">
                                <?= count(array_filter($animalsOfType, fn($a) => ($a['status'] ?? '') === 'For Sale')) ?> For Sale
                            </span>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="<?= $collapseId ?>">
                        <div class="animal-grid-container">
                            <?php foreach ($animalsOfType as $animal): 
                                // Calculate readiness and get status details
                                $readinessPercentage = calculateReadinessPercentage($animal);
                                $statusDetails = getStatusDetails($animal['status'] ?? 'Not Yet Ready');
                    
                    // Determine badge class based on animal type
                    $typeLower = strtolower($animal['type'] ?? '');
                    $badgeClass = '';
                    
                    // More precise type matching with fallback
                    switch ($typeLower) {
                        case 'chick':
                            $badgeClass = 'badge-chick';
                            break;
                        case 'stag':
                            $badgeClass = 'badge-stag';
                            break;
                        case 'rooster':
                            $badgeClass = 'badge-rooster';
                            break;
                        case 'hen':
                            $badgeClass = 'badge-hen';
                            break;
                        default:
                            $badgeClass = 'badge-chick'; // Default fallback
                    }
                    
                    // Gender badge styling
                    $gender = strtolower($animal['gender'] ?? 'male');
                    $genderClass = $gender === 'male' ? 'male-badge' : 'female-badge';
                    $genderSymbol = $gender === 'male' ? '♂' : '♀';
                ?>
                <div class="animal-card">
                    <div class="animal-header">
                        <div>
                            <span class="badge <?= $badgeClass ?> me-2"><?= htmlspecialchars($animal['type'] ?? '') ?></span>
                            <span class="text-white">#<?= $animal['id'] ?? '' ?></span>
                        </div>
                        <span class="badge <?= $genderClass ?> gender-badge">
                            <?= $genderSymbol ?> <?= ucfirst($animal['gender'] ?? '') ?>
                        </span>
                    </div>
                    
                    <div class="animal-body">
                        <div class="animal-details">
                            <div class="animal-detail">
                                <span class="detail-label">Age</span>
                                <span class="detail-value"><?= htmlspecialchars(formatAge($animal['age'])) ?> days</span>
                            </div>
                            <div class="animal-detail">
                                <span class="detail-label">Breed</span>
                                <span class="detail-value"><?= htmlspecialchars($animal['breed']) ?></span>
                            </div>
                            <div class="animal-detail">
                                <span class="detail-label">Mark</span>
                                <span class="detail-value"><?= htmlspecialchars($animal['mark'] ?? 'None') ?></span>
                            </div>
                        </div>
                        
                        <div class="status-section">
                            <div class="status-container">
                                <span class="status-badge status-<?= $statusDetails['color'] ?>"
                                      data-bs-toggle="tooltip" 
                                      title="<?= $statusDetails['description'] ?>">
                                    <i class="fas <?= $statusDetails['icon'] ?> me-1"></i>
                                    <?= htmlspecialchars($animal['status'] ?? 'Not Yet Ready') ?>
                                </span>
                                
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Growth Progress</span>
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
                        <a href="view_animal.php?id=<?= $animal['id'] ?? '' ?>" class="action-btn view-btn">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                        
                        <button class="action-btn archive-btn" 
                                data-id="<?= $animal['id'] ?? '' ?>"
                                data-bs-toggle="modal" 
                                data-bs-target="#archiveModal">
                            <i class="fas fa-archive me-1"></i> Archive
                        </button>
                    </div>
                </div>
                            <?php endforeach; ?>
                        </div>
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
        <a href="archive.php" class="nav-item">
            <i class="fas fa-archive"></i>
            <span>Archive</span>
        </a>
    </div>
    
    <!-- Sidebar Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar">
        <div class="sidebar-header-modern d-flex align-items-center justify-content-between px-3 py-3">
            <div class="d-flex align-items-center gap-2">
                <img src="/assets/images/FeatherTech.jpg" alt="Logo" class="app-logo me-2" style="width:40px;height:40px;">
                <span class="sidebar-title">Feather<span style="color: var(--poultry-yellow);">Tech</span></span>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="sidebar-user-section text-center py-4 px-3">
            <div class="sidebar-avatar-wrapper mx-auto mb-2">
                <img src="<?= !empty($_SESSION['avatar_path']) ? htmlspecialchars($_SESSION['avatar_path']) : 'assets/images/solo_leveling.jpeg' ?>"
                    class="sidebar-user-avatar"
                    onerror="this.src='assets/images/solo_leveling.jpeg'"
                    alt="Profile Avatar">
            </div>
            <div class="sidebar-user-name fw-semibold mb-0"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
            <div class="sidebar-user-role small text-muted mb-1">Role: <?= ucfirst($_SESSION['role'] ?? 'User') ?></div>
        </div>

        <div class="sidebar-section-label text-uppercase small fw-bold px-3 mt-2 mb-1 text-muted">Poultry Management</div>
        <div class="list-group list-group-flush sidebar-list-group">
            <a href="add_animal.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-plus sidebar-icon"></i> Add Poultry
            </a>
            <a href="archive.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-archive sidebar-icon"></i> Archived Records
            </a>
        </div>
        <div class="sidebar-section-label text-uppercase small fw-bold px-3 mt-3 mb-1 text-muted">User & Tools</div>
        <div class="list-group list-group-flush sidebar-list-group">
            <a href="register.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-user-plus sidebar-icon"></i> Add User
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-cog sidebar-icon"></i> Settings
            </a>
            <?php if (($_SESSION['role'] ?? '') === 'super admin'): ?>
            <a href="manage_admin.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-user-shield sidebar-icon"></i> Manage Admin Account
            </a>
            <a href="admin_notifications.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-tasks sidebar-icon"></i> Admin Tasks
            </a>
            <?php elseif (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="vaccination_tasks.php" class="list-group-item list-group-item-action sidebar-link">
                <i class="fas fa-tasks sidebar-icon"></i> Vaccination Tasks
            </a>
            <?php endif; ?>
            <a href="logout.php" class="list-group-item list-group-item-action sidebar-link text-danger">
                <i class="fas fa-sign-out-alt sidebar-icon"></i> Logout
            </a>
        </div>
    </div>

    <!-- Archive Modal -->
    <div class="modal fade" id="archiveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-archive me-2"></i> Confirm Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this record? It will be moved to the archive history.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmArchive">
                        <i class="fas fa-archive me-1"></i> Archive
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <!-- Bootstrap 5.3.2 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
            crossorigin="anonymous"></script>
    <script>
        // Initialize tooltips and notification handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap collapse with persistence
            const categoryElements = document.querySelectorAll('.animal-category .category-header');
            categoryElements.forEach(element => {
                const targetId = element.getAttribute('data-bs-target');
                const target = document.querySelector(targetId);
                
                // Check localStorage for saved state
                const isExpanded = localStorage.getItem(`category_${targetId}`) !== 'false';
                
                // Initialize collapse with saved state
                const collapse = new bootstrap.Collapse(target, {
                    toggle: false
                });
                
                if (!isExpanded) {
                    collapse.hide();
                }
                
                // Save state when toggled
                element.addEventListener('click', () => {
                    setTimeout(() => {
                        const isNowExpanded = !target.classList.contains('show');
                        localStorage.setItem(`category_${targetId}`, isNowExpanded);
                    }, 10);
                });
            });
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Mark notification as read
            document.querySelectorAll('.mark-as-read, .notification-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.closest('.dropdown-menu') || e.target.closest('.dropdown')) {
                        return; // Don't mark as read if clicking on dropdown menu
                    }
                    
                    const notificationId = this.dataset.notificationId;
                    if (!notificationId) return;
                    
                    // Mark as read visually
                    this.classList.remove('unread');
                    
                    // Send AJAX request to mark as read
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ notification_id: notificationId })
                    });
                });
            });
            
            // Mark all as read
            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    // Update notification count
                    const badge = document.querySelector('.notification-count');
                    if (badge) {
                        badge.remove();
                    }
                    
                    // Send AJAX request to mark all as read
                    fetch('mark_all_notifications_read.php', {
                        method: 'POST'
                    });
                });
            }
            
            // Archive modal handling
            document.querySelectorAll('.archive-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    document.getElementById('confirmArchive').href = `delete_animal.php?id=${id}`;
                });
            });
            
            // Initialize image carousel with smooth animations
            const carousel = document.querySelector('#poultryCarousel');
            if (carousel) {
                const carouselInstance = new bootstrap.Carousel(carousel, {
                    interval: 5000,
                    touch: true,
                    wrap: true,
                    pause: 'hover',
                    keyboard: true
                });
                
                // Add hover effects to carousel items
                const carouselItems = document.querySelectorAll('.carousel-item');
                carouselItems.forEach(item => {
                    item.addEventListener('mouseenter', () => {
                        item.style.transform = 'scale(1.02)';
                        item.style.transition = 'transform 0.3s ease';
                    });
                    item.addEventListener('mouseleave', () => {
                        item.style.transform = 'scale(1)';
                    });
                });
                
                // Add smooth transition between slides
                carousel.addEventListener('slide.bs.carousel', function (e) {
                    const activeItem = e.relatedTarget;
                    const items = carousel.querySelectorAll('.carousel-item');
                    items.forEach(item => {
                        item.style.transition = 'opacity 0.6s ease-in-out';
                        if (item !== activeItem) {
                            item.style.opacity = '0.6';
                        } else {
                            item.style.opacity = '1';
                        }
                    });
                });
            }
            
            // Refresh notifications button
            document.getElementById('refreshNotificationsBtn')?.addEventListener('click', function() {
                this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
                fetch('refresh_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            showToast('Error refreshing notifications', 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Network error: ' + error.message, 'danger');
                    })
                    .finally(() => {
                        this.innerHTML = '<i class="fas fa-sync-alt"></i>';
                    });
            });
            
            // Add hover animations to cards
            document.querySelectorAll('.animal-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 24px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
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
            toastEl.style.transition = 'all 0.3s ease';
            
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toastEl);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                toastEl.style.opacity = '0';
                setTimeout(() => {
                    toastContainer.remove();
                }, 300);
            }, 5000);
        }
    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only run this for admins who have the notification dropdown
    if (document.querySelector('#notificationDropdown')) {
        const notificationCountBadge = document.querySelector('.notification-count');
        const notificationDropdownMenu = document.querySelector('.notification-dropdown');

        function fetchAdminNotifications() {
            fetch('fetch_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications) {
                        updateNotificationUI(data.notifications);
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }

        function updateNotificationUI(notifications) {
            const unreadNotifications = notifications.filter(n => !n.is_read);
            
            if (unreadNotifications.length > 0) {
                notificationCountBadge.textContent = unreadNotifications.length;
                notificationCountBadge.style.display = 'block';
            } else {
                notificationCountBadge.style.display = 'none';
            }

            if (notifications.length > 0) {
                notificationDropdownMenu.innerHTML = ''; // Clear existing items
                notifications.forEach(notification => {
                    const item = document.createElement('li');
                    const isReadClass = notification.is_read ? 'text-muted' : 'fw-bold';
                    item.innerHTML = `
                        <a class="dropdown-item notification-item ${isReadClass}" href="#" data-id="${notification.id}">
                            <p class="mb-1 small">${notification.message}</p>
                            <small class="text-muted">${timeAgo(notification.created_at)}</small>
                        </a>`;
                    notificationDropdownMenu.appendChild(item);
                });
            } else {
                notificationDropdownMenu.innerHTML = '<li><a class="dropdown-item text-center text-muted" href="#">No new notifications</a></li>';
            }
        }

        notificationDropdownMenu.addEventListener('click', function(e) {
            const target = e.target.closest('.notification-item');
            if (target) {
                e.preventDefault();
                const notificationId = target.dataset.id;

                // Mark as read in UI immediately
                target.classList.remove('fw-bold');
                target.classList.add('text-muted');

                const formData = new FormData();
                formData.append('notification_id', notificationId);
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

                fetch('mark_notification_read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Notification marked as read', 'success');
                        // Update notification count
                        const countBadge = document.querySelector('.notification-count');
                        if (countBadge && countBadge.textContent) {
                            const currentCount = parseInt(countBadge.textContent);
                            if (currentCount > 1) {
                                countBadge.textContent = currentCount - 1;
                            } else {
                                countBadge.style.display = 'none';
                            }
                        }
                        // Redirect after a short delay to show the success message
                        setTimeout(() => {
                            window.location.href = 'vaccination_tasks.php';
                        }, 1000);
                    } else {
                        showToast(data.message || 'Failed to mark notification as read', 'danger');
                        // Still redirect after showing error
                        setTimeout(() => {
                            window.location.href = 'vaccination_tasks.php';
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                    showToast('Error marking notification as read', 'danger');
                    // Still redirect after showing error
                    setTimeout(() => {
                        window.location.href = 'vaccination_tasks.php';
                    }, 2000);
                });
            }
        });

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + "y ago";
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + "m ago";
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + "d ago";
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + "h ago";
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + "m ago";
            return Math.floor(seconds) + "s ago";
        }

        // Initial fetch and periodic refresh
        fetchAdminNotifications();
        setInterval(fetchAdminNotifications, 30000); // Refresh every 30 seconds
    }
});
</script>
</body>
</html>