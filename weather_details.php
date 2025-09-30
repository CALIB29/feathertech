<?php
include 'includes/db.php';
include 'includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in or is a secret user
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_secret_user'])) {
    // Redirect to login page if not authenticated
    header("Location: index.php");
    exit();
}

// Function to fetch weather data
function fetchWeather($apiKey, $location) {
    $url = "http://api.openweathermap.org/data/2.5/weather?q={$location}&appid={$apiKey}&units=metric";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Fetch weather data
$weatherApiKey = '296a750f9c53ec1ea580fbc2ede492dd'; 
$location = 'Becuran,PH'; 
$weatherData = fetchWeather($weatherApiKey, $location);

$temperature = $weatherData['main']['temp'] ?? null;
$humidity = $weatherData['main']['humidity'] ?? null;
$windSpeed = $weatherData['wind']['speed'] ?? null;
$sunrise = $weatherData['sys']['sunrise'] ?? null;
$sunset = $weatherData['sys']['sunset'] ?? null;
$weatherCondition = $weatherData['weather'][0]['main'] ?? 'Clear';
$weatherIcon = $weatherData['weather'][0]['icon'] ?? '01d';

// Convert sunrise and sunset times to readable format
$sunriseTime = date("H:i", $sunrise);
$sunsetTime = date("H:i", $sunset);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Weather Details - FeatherTech</title>
    
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" 
          crossorigin="anonymous">
    <!-- Font Awesome 6.4.2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" 
          integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Weather Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/weather-icons/2.0.12/css/weather-icons.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary-color: #7209b7;
            --accent-color: #f72585;
            --success-color: #4cc9a0;
            --warning-color: #ff9e00;
            --danger-color: #ef476f;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --card-bg: rgba(255, 255, 255, 0.1);
            --card-hover-bg: rgba(255, 255, 255, 0.15);
            --text-color: #fff;
            --text-muted: rgba(255, 255, 255, 0.8);
            --border-radius: 12px;
            --box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #4f6bff, #3a0ca3);
            color: var(--text-color);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 2rem 0;
            margin: 0;
        }

        /* Back to Dashboard Link */
        .dashboard-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: var(--primary-color);
            color: var(--text-color);
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .dashboard-link:hover {
            background: var(--hover-color);
        }

        .weather-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
        }
        
        .weather-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .weather-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .weather-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary-light), transparent);
            border-radius: 3px;
        }

        .weather-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #fff, #e0e0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .weather-header p {
            font-size: 1.2rem;
            opacity: 0.8;
        }

        .temperature-graph h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .weather-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: var(--transition);
            height: 100%;
        }
        
        .weather-card:hover {
            background: var(--card-hover-bg);
            transform: translateY(-3px);
        }
        
        .weather-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-light);
        }
        
        .weather-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-color);
        }
        
        .weather-card p {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        
        .weather-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
            margin: 0.5rem 0;
        }

        .comfort-level h2, .wind-info h2, .sunrise-sunset h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .sunrise-sunset .sun-path span {
            font-size: 1.1rem;
        }

        /* Weather Condition Icons */
        .weather-condition {
            font-size: 4rem;
            margin: 1rem 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.2));
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }
        
        /* Temperature Display */
        .temperature-display {
            font-size: 4rem;
            font-weight: 700;
            margin: 1rem 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            position: relative;
            display: inline-block;
        }
        
        .temperature-display::after {
            content: '°C';
            font-size: 2rem;
            position: absolute;
            top: 0.5rem;
            right: -2rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .weather-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .temperature-display {
                font-size: 3rem;
            }
            
            .weather-card {
                margin-bottom: 1rem;
            }
        }

        .windmill .blades {
            width: 100%;
            height: 100%;
            position: absolute;
            animation: spin 5s linear infinite;
        }

        .windmill .blades img {
            width: 100%;
            height: 100%;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 3D Sun Path Animation */
        .sun-path-3d {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            height: 100px;
            margin: 20px 0;
        }

        .sun-path-3d .sun {
            width: 50px;
            height: 50px;
            background: #FFD700;
            border-radius: 50%;
            position: absolute;
            animation: sun-path 10s linear infinite;
        }

        @keyframes sun-path {
            0% { left: 0; top: 50px; }
            50% { left: 50%; top: 0; }
            100% { left: 100%; top: 50px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn btn-outline-light mb-4">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        
        <div class="weather-container">
            <div class="weather-header">
                <h1>Weather in <?php echo htmlspecialchars(explode(',', $location)[0]); ?></h1>
                <p class="text-muted"><?php echo date('l, F j, Y'); ?></p>
                
                <div class="d-flex justify-content-center align-items-center my-4">
                    <div class="text-center">
                        <div class="weather-condition">
                            <?php 
                            $iconClass = '';
                            switch($weatherCondition) {
                                case 'Clear':
                                    $iconClass = 'wi-day-sunny';
                                    break;
                                case 'Clouds':
                                    $iconClass = 'wi-cloudy';
                                    break;
                                case 'Rain':
                                case 'Drizzle':
                                    $iconClass = 'wi-rain';
                                    break;
                                case 'Thunderstorm':
                                    $iconClass = 'wi-thunderstorm';
                                    break;
                                case 'Snow':
                                    $iconClass = 'wi-snow';
                                    break;
                                default:
                                    $iconClass = 'wi-day-cloudy';
                            }
                            ?>
                            <i class="wi <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="temperature-display"><?php echo round($temperature); ?></div>
                        <h3 class="mb-0"><?php echo $weatherCondition; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Temperature Graph -->
            <div class="temperature-graph">
                <h2>5-Day Temperature Forecast</h2>
                <canvas id="temperatureChart" width="400" height="200"></canvas>
            </div>

            <!-- Comfort Level Section -->
            <!-- Weather Forecast (Example - would need additional API calls for actual forecast) -->
            <div class="weather-card mt-4">
                <h3 class="mb-4">5-Day Forecast</h3>
                <div class="row g-3">
                    <?php
                    // This is a placeholder - in a real app, you would fetch forecast data
                    $forecastDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
                    $forecastIcons = ['wi-day-sunny', 'wi-cloudy', 'wi-rain', 'wi-day-cloudy-high', 'wi-thunderstorm'];
                    $forecastTemps = [28, 26, 24, 27, 25];
                    
                    foreach ($forecastDays as $index => $day): 
                        if ($index > 4) break; // Limit to 5 days
                    ?>
                    <div class="col">
                        <div class="text-center p-2">
                            <div class="mb-2"><?php echo $day; ?></div>
                            <div class="mb-2">
                                <i class="wi <?php echo $forecastIcons[$index]; ?>" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="fw-bold"><?php echo $forecastTemps[$index]; ?>°C</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Weather Alerts -->
            <?php if (isset($weatherData['weather'][0]['main']) && in_array($weatherData['weather'][0]['main'], ['Thunderstorm', 'Dust', 'Ash', 'Squall', 'Tornado'])): ?>
            <div class="alert alert-warning mt-4" role="alert">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Weather Alert</h4>
                <p>Severe weather condition detected: <strong><?php echo $weatherData['weather'][0]['description']; ?></strong>. Please take necessary precautions.</p>
            </div>
            <?php endif; ?>
            
            <!-- Sunrise/Sunset with 3D Sun Path -->
            <div class="sunrise-sunset">
                <h2>Sunrise & Sunset</h2>
                <div class="sun-path-3d">
                    <div class="sun"></div>
                </div>
                <div class="sun-path">
                    <img src="https://img.icons8.com/ios-filled/50/ffffff/sunrise.png" alt="Sunrise">
                    <span><?php echo $sunriseTime; ?></span>
                    <img src="https://img.icons8.com/ios-filled/50/ffffff/sunset.png" alt="Sunset">
                    <span><?php echo $sunsetTime; ?></span>
                </div>
            </div>
            <a href="dashboard.php" class="dashboard-link">Back to Dashboard</a>
        </div>

        <!-- Chart.js for Temperature Graph -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Temperature Graph Data
            const ctx = document.getElementById('temperatureChart').getContext('2d');
            const temperatureChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
                    datasets: [{
                        label: 'High Temperature (°C)',
                        data: [30, 32, 31, 29, 28],
                        borderColor: '#FFA500',
                        backgroundColor: 'rgba(255, 165, 0, 0.2)',
                        fill: true,
                    }, {
                        label: 'Low Temperature (°C)',
                        data: [22, 23, 21, 20, 19],
                        borderColor: '#1E90FF',
                        backgroundColor: 'rgba(30, 144, 255, 0.2)',
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: '5-Day Temperature Forecast'
                        }
    <!-- Chart.js for Temperature Graph -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Temperature Graph Data
        const ctx = document.getElementById('temperatureChart').getContext('2d');
        const temperatureChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
                datasets: [{
                    label: 'High Temperature (°C)',
                    data: [30, 32, 31, 29, 28],
                    borderColor: '#FFA500',
                    backgroundColor: 'rgba(255, 165, 0, 0.2)',
                    fill: true,
                }, {
                    label: 'Low Temperature (°C)',
                    data: [22, 23, 21, 20, 19],
                    borderColor: '#1E90FF',
                    backgroundColor: 'rgba(30, 144, 255, 0.2)',
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: '5-Day Temperature Forecast'
                    }
                }
            }
        });
    </script>
</body>
</html>