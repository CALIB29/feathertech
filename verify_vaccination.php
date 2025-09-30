<?php
include 'includes/db.php';
include 'includes/auth.php';

// Initialize variables
$animal = null;
$vaccinationRecords = [];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $animalId = trim($_POST['animal_id'] ?? '');
    
    if (empty($animalId)) {
        $error = 'Please enter an Animal ID or scan a QR code';
    } else {
        try {
            // First check if database connection is working
            if (!isset($pdo) || !$pdo) {
                throw new PDOException('Database connection not established');
            }

            // Check if required tables exist
            $requiredTables = ['animals', 'vaccination_tasks', 'users'];
            foreach ($requiredTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() === 0) {
                    throw new PDOException("Required table '$table' does not exist");
                }
            }

            // Clean and prepare search input
            $searchInput = trim($animalId);

            // Check if input is a URL and extract ID from it
            $actualSearchValue = $searchInput;
            if (strpos($searchInput, 'view_animal.php?id=') !== false) {
                // Extract ID from URL like "https://afeathertech.com/view_animal.php?id=36"
                if (preg_match('/[?&]id=(\d+)/', $searchInput, $matches)) {
                    $actualSearchValue = $matches[1]; // Extract just the ID number
                }
            }

            // Log the search for debugging (only for admins)
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'super admin') {
                error_log("Vaccination Search Debug - Original Input: '$animalId', Cleaned: '$searchInput', Parsed ID: '$actualSearchValue'");
            }

            // Get animal details - search by ID, QR code, or mark with flexible matching
            $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ? OR qr_code = ? OR mark = ? OR qr_code LIKE ?");
            $searchPattern = '%' . $actualSearchValue . '%';
            $stmt->execute([$actualSearchValue, $actualSearchValue, $actualSearchValue, $searchPattern]);
            $animal = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debug: Log what we found
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'super admin') {
                $rowCount = $stmt->rowCount();
                error_log("Vaccination Search Debug - Rows found: $rowCount, Animal: " . ($animal ? 'Found' : 'Not found'));
                if ($animal) {
                    error_log("Vaccination Search Debug - Found animal ID: {$animal['id']}, QR: {$animal['qr_code']}, Mark: {$animal['mark']}");
                }
            }

            if ($animal) {
                // Get vaccination records
                $stmt = $pdo->prepare("
                    SELECT vt.*, u1.username as assigned_by_name, u2.username as completed_by_name
                    FROM vaccination_tasks vt
                    LEFT JOIN users u1 ON vt.assigned_by = u1.id
                    LEFT JOIN users u2 ON vt.completed_by = u2.id
                    WHERE vt.animal_id = ? AND vt.status = 'completed'
                    ORDER BY vt.completed_date DESC
                ");
                $stmt->execute([$animal['id']]);
                $vaccinationRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($vaccinationRecords)) {
                    $error = 'No vaccination records found for this animal';
                } else {
                    $success = 'Vaccination records found';
                }
            } else {
                // Provide more helpful debugging info
                $debugInfo = '';
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'super admin') {
                    $debugInfo = "<br><small class='text-muted'>Debug: Searched for '$searchInput' - parsed as ID '$actualSearchValue' but no matching animal found. Check that the ID exists in the database.</small>";
                }
                $error = 'Animal not found. Please check the ID and try again.' . $debugInfo;
            }
        } catch (PDOException $e) {
            error_log("Database Error in verify_vaccination.php: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // Provide more specific error messages based on the exception
            if (strpos($e->getMessage(), 'connection') !== false) {
                $error = 'Database connection failed. Please contact the administrator.';
            } elseif (strpos($e->getMessage(), 'does not exist') !== false) {
                $error = 'Database structure issue. Please contact the administrator.';
            } else {
                $error = 'An error occurred while fetching records. Please try again.';
            }

            // Log additional debugging info
            error_log("Error details - Animal ID: $animalId, PDO Error Code: " . $e->getCode());
        } catch (Exception $e) {
            error_log("General Error in verify_vaccination.php: " . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verify Vaccination - FeatherTech</title>
    
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
    
    <style>
        :root {
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
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: var(--dark-color);
        }
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .vaccine-card {
            border-left: 4px solid var(--success-color);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .vaccine-card:hover {
            transform: translateX(5px);
        }
        
        .qr-scanner-container {
            border: 2px dashed var(--medium-gray);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .qr-scanner-container:hover {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .animal-details {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .card {
                border-radius: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-syringe me-2"></i>
                        <span>Verify Vaccination Record</span>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super admin'): ?>
                                    <hr>
                                    <small class="text-muted">
                                        <strong>Debug Info:</strong><br>
                                        PHP Version: <?= PHP_VERSION ?><br>
                                        Database Host: <?= $host ?? 'Not available' ?><br>
                                        Database Name: <?= $db ?? 'Not available' ?><br>
                                        PDO Connection: <?= isset($pdo) ? 'Established' : 'Failed' ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>Success:</strong> <?= htmlspecialchars($success) ?>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super admin'): ?>
                                    <hr>
                                    <small class="text-muted">
                                        Found <?= count($vaccinationRecords) ?> vaccination records
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="animal_id" class="form-label">Animal ID, QR Code, or Mark</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control form-control-lg" id="animal_id" 
                                               name="animal_id" placeholder="Scan QR code or enter ID/QR Code/Mark" 
                                               value="<?= htmlspecialchars($_POST['animal_id'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="search" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </div>
                            </div>
                            
                            <div class="text-center my-3">
                                <span class="text-muted">or</span>
                            </div>
                            
                            <div class="qr-scanner-container" id="qrScanner">
                                <i class="fas fa-qrcode fa-3x mb-3" style="color: var(--primary-color);"></i>
                                <p class="mb-0">Click to scan QR code</p>
                                <small class="text-muted">Point your camera at the animal's QR code (works with URL-based QR codes)</small>
                            </div>
                        </form>
                        
                        <?php if ($animal): ?>
                            <div class="animal-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Animal Details</h5>
                                        <p class="mb-1"><strong>ID:</strong> <?= htmlspecialchars($animal['id'] ?? 'N/A') ?></p>
                                        <p class="mb-1"><strong>QR Code:</strong> <?= htmlspecialchars($animal['qr_code'] ?? 'N/A') ?></p>
                                        <p class="mb-1"><strong>Mark:</strong> <?= htmlspecialchars($animal['mark'] ?? 'N/A') ?></p>
                                        <p class="mb-1"><strong>Type:</strong> <?= htmlspecialchars(ucfirst($animal['type'] ?? 'N/A')) ?></p>
                                        <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars(ucfirst($animal['breed'] ?? 'N/A')) ?></p>
                                        <p class="mb-0"><strong>Gender:</strong> <?= htmlspecialchars(ucfirst($animal['gender'] ?? 'N/A')) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Vaccination Status</h5>
                                        <p class="mb-1">
                                            <strong>Total Vaccinations:</strong> 
                                            <span class="badge bg-primary"><?= count($vaccinationRecords) ?></span>
                                        </p>
                                        <?php if (!empty($vaccinationRecords)): ?>
                                            <p class="mb-1">
                                                <strong>Last Vaccination:</strong> 
                                                <?= date('M d, Y', strtotime($vaccinationRecords[0]['completed_date'])) ?>
                                            </p>
                                            <p class="mb-0">
                                                <strong>Next Due:</strong> 
                                                <?php 
                                                    $lastVaccineDate = new DateTime($vaccinationRecords[0]['completed_date']);
                                                    $nextDueDate = $lastVaccineDate->modify('+1 year');
                                                    echo $nextDueDate->format('M d, Y');
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($vaccinationRecords)): ?>
                                <h5 class="mt-4 mb-3">Vaccination History</h5>
                                <div class="vaccine-records">
                                    <?php foreach ($vaccinationRecords as $record): ?>
                                        <div class="card vaccine-card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?= htmlspecialchars(ucfirst($record['recommended_vaccine'])) ?>
                                                        </h6>
                                                        <p class="text-muted small mb-1">
                                                            <i class="far fa-calendar-alt me-1"></i>
                                                            <?= date('M d, Y', strtotime($record['completed_date'])) ?>
                                                        </p>
                                                        <?php if (!empty($record['notes'])): ?>
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($record['notes'])) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i> Verified
                                                    </span>
                                                </div>
                                                <div class="mt-2 text-muted small">
                                                    <span class="me-3">
                                                        <i class="fas fa-user-md me-1"></i> 
                                                        <?= htmlspecialchars($record['completed_by_name'] ?? 'Unknown') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <button class="btn btn-outline-primary" onclick="window.print()">
                                        <i class="fas fa-print me-2"></i>Print Record
                                    </button>
                                    <a href="dashboard.php" class="btn btn-primary">
                                        <i class="fas fa-home me-2"></i>Back to Dashboard
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center text-muted small">
                    <p>© <?= date('Y') ?> FeatherTech. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
            crossorigin="anonymous"></script>
    
    <!-- QR Code Scanner -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize QR Scanner
            const qrScanner = document.getElementById('qrScanner');
            const animalIdInput = document.getElementById('animal_id');
            let html5QrCode = null;
            
            qrScanner.addEventListener('click', function() {
                if (html5QrCode && html5QrCode.isScanning) {
                    stopQrScanner();
                    return;
                }
                
                // Start QR Scanner
                html5QrCode = new Html5Qrcode("qrScanner");
                
                html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: 250
                    },
                    (decodedText, decodedResult) => {
                        // Handle scanned code
                        let scannedValue = decodedText;

                        // Check if it's a URL and extract ID
                        if (scannedValue.includes('view_animal.php?id=')) {
                            let idMatch = scannedValue.match(/[?&]id=(\d+)/);
                            if (idMatch) {
                                scannedValue = idMatch[1]; // Use just the ID
                            }
                        }

                        animalIdInput.value = scannedValue;
                        stopQrScanner();
                        document.querySelector('form').submit();
                    },
                    (errorMessage) => {
                        // Error handling
                        console.log(errorMessage);
                    }
                ).catch((err) => {
                    console.error("QR Scanner error:", err);
                    alert("Error starting QR scanner. Make sure you've granted camera permissions.");
                });
                
                // Update UI
                qrScanner.innerHTML = `
                    <div class="text-center">
                        <div id="reader" style="width: 100%;"></div>
                        <button class="btn btn-sm btn-outline-danger mt-3" onclick="stopQrScanner()">
                            <i class="fas fa-times me-1"></i> Stop Scanner
                        </button>
                    </div>
                `;
            });
            
            // Function to stop QR scanner
            window.stopQrScanner = function() {
                if (html5QrCode) {
                    html5QrCode.stop().then((ignore) => {
                        // QR Code scanning is stopped.
                        qrScanner.innerHTML = `
                            <i class="fas fa-qrcode fa-3x mb-3" style="color: var(--primary-color);"></i>
                            <p class="mb-0">Click to scan QR code</p>
                            <small class="text-muted">Point your camera at the animal's QR code (works with URL-based QR codes)</small>
                        `;
                    }).catch((err) => {
                        console.error("Error stopping QR scanner:", err);
                    });
                }
            };
            
            // Close scanner when clicking outside
            document.addEventListener('click', function(e) {
                if (!qrScanner.contains(e.target) && html5QrCode && html5QrCode.isScanning) {
                    stopQrScanner();
                }
            });
        });
    </script>
</body>
</html>
