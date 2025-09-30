<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Include necessary files
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/weather.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in or is a secret user
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_secret_user'])) {
    header("Location: index.php");
    exit();
}

// Get current time and determine the appropriate greeting
$currentHour = date("H"); // Get the current hour in 24-hour format
if ($currentHour >= 5 && $currentHour < 12) {
    $greeting = "Good Morning";
} elseif ($currentHour >= 12 && $currentHour < 18) {
    $greeting = "Good Afternoon";
} elseif ($currentHour >= 18 && $currentHour < 24) {
    $greeting = "Good Evening";
} else {
    $greeting = "Good Night"; // For times between 12 AM and 4:59 AM
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

// Fetch animals from the database
try {
    $stmt = $pdo->query("SELECT * FROM animals");
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $animals = [];
    error_log("Database Error: " . $e->getMessage());
}

// Function to format age in months
function formatAge($days) {
    return floor($days / 30) . " month(s)";
}

// Fetch data for charts
function fetchAnimalGrowthData() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count, WEEK(created_at) as week FROM animals GROUP BY WEEK(created_at)");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        error_log("Error fetching animal growth data: " . $e->getMessage());
        return [];
    }
}

function fetchEggProductionData() {
    global $pdo;
    try {
        // Check if the table and columns exist
        $stmt = $pdo->query("SHOW COLUMNS FROM egg_production LIKE 'eggs_produced'");
        if ($stmt->rowCount() === 0) {
            return []; // Return empty array if the column does not exist
        }

        // Fetch egg production data
        $stmt = $pdo->query("SELECT SUM(eggs_produced) as total, DATE(production_date) as day FROM egg_production GROUP BY DATE(production_date)");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        error_log("Error fetching egg production data: " . $e->getMessage());
        return []; // Return empty array on error
    }
}

function fetchMortalityData() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT cause_of_death, COUNT(*) as count FROM mortality_records GROUP BY cause_of_death");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        error_log("Error fetching mortality data: " . $e->getMessage());
        return [];
    }
}

$animalGrowthData = fetchAnimalGrowthData();
$eggProductionData = fetchEggProductionData();
$mortalityData = fetchMortalityData();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FeatherTech Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .navbar {
            background: #0d44d1;
        }
        .sidebar {
            width: 250px;
            background: #0d44d1;
            color: #fff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 10px 15px;
            transition: background 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: #0d44d1;
            color: #fff;
        }
        .badge-ready {
            background: #4bc986;
        }
        .badge-not-ready {
            background: #ff6b6b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h4>FeatherTech</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-plus"></i> Add Animal</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?= $greeting ?>, Super Admin</h2>
            <div class="weather-alert">
                <span><?= $weatherCondition ?> - <?= $temperature ?>°C</span>
                <img src="https://openweathermap.org/img/wn/<?= $weatherIcon ?>@2x.png" alt="Weather Icon">
            </div>
        </div>

        <!-- Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Total Animals</div>
                    <div class="card-body">
                        <h3><?= count($animals) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Ready for Harvest</div>
                    <div class="card-body">
                        <h3><?= count(array_filter($animals, fn($a) => $a['status'] === 'Ready for Harvesting')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Weather Alerts</div>
                    <div class="card-body">
                        <p><?= $weatherCondition ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mt-4">
            <!-- Animal Growth Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Animal Growth</div>
                    <div class="card-body">
                        <canvas id="animalGrowthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Egg Production Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Egg Production</div>
                    <div class="card-body">
                        <canvas id="eggProductionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Mortality Rates Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Mortality Rates</div>
                    <div class="card-body">
                        <canvas id="mortalityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Animal Table -->
        <div class="card mt-4">
            <div class="card-header">Animal Management</div>
            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Age</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($animals as $animal): ?>
                        <tr>
                            <td><?= $animal['id'] ?></td>
                            <td><?= $animal['type'] ?></td>
                            <td><?= formatAge($animal['age']) ?></td>
                            <td>
                                <span class="badge <?= $animal['status'] === 'Not Yet Ready' ? 'badge-not-ready' : 'badge-ready' ?>">
                                    <?= $animal['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="#" class="btn btn-info btn-sm">View</a>
                                <a href="#" class="btn btn-warning btn-sm">Update</a>
                                <a href="#" class="btn btn-danger btn-sm">Archive</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Animal Growth Chart (Line Chart)
        const animalGrowthCtx = document.getElementById('animalGrowthChart').getContext('2d');
        const animalGrowthChart = new Chart(animalGrowthCtx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                datasets: [{
                    label: 'Animal Growth',
                    data: <?= json_encode($animalGrowthData) ?>,
                    borderColor: '#0d44d1',
                    backgroundColor: 'rgba(13, 68, 209, 0.1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Egg Production Chart (Bar Chart)
        const eggProductionCtx = document.getElementById('eggProductionChart').getContext('2d');
        const eggProductionChart = new Chart(eggProductionCtx, {
            type: 'bar',
            data: {
                labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
                datasets: [{
                    label: 'Eggs Produced',
                    data: <?= json_encode($eggProductionData) ?>,
                    backgroundColor: '#4bc986',
                    borderColor: '#4bc986',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Mortality Rates Chart (Pie Chart)
        const mortalityCtx = document.getElementById('mortalityChart').getContext('2d');
        const mortalityChart = new Chart(mortalityCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_keys($mortalityData)) ?>,
                datasets: [{
                    label: 'Mortality Rates',
                    data: <?= json_encode(array_values($mortalityData)) ?>,
                    backgroundColor: [
                        '#ff6b6b',
                        '#0d44d1',
                        '#4bc986',
                        '#ffc107',
                        '#17a2b8'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>