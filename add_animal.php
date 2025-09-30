<?php
include 'includes/db.php';
include 'qr/lib/full/qrlib.php'; // Ensure this path is correct

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $age = $_POST['age'];
    $breed = $_POST['breed'];
    $mark = $_POST['mark'];
    $breed_season = $_POST['breed_season'];

    // Convert age to months if the age is provided in days
    if ($age > 0) {
        $age = round($age / 30); // Assuming average month length of 30 days
    }

    // Create the QR code data string
    $data = "ID: " . time() . ", Type: " . $type . ", Age: " . $age . " months, Breed: " . $breed . ", Mark: " . $mark . ", Breed Season: " . $breed_season;

    // Path to save the generated QR code image
    $filePath = 'assets/images/qrcode_' . time() . '.png';  // Ensure a unique file name to prevent overwriting

    // Generate the QR code and save it as an image
    QRcode::png($data, $filePath, QR_ECLEVEL_L, 3, 4); // Adjust the parameters as needed

    // Save the animal data in the database
    $stmt = $pdo->prepare("INSERT INTO animals (type, age, breed, mark, breed_season, qr_code) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$type, $age, $breed, $mark, $breed_season, $filePath]);

    echo 'QR Code generated successfully!';

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Animal | FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d44d1;
            --secondary-color: #15dfe6;
            --success-color: #4bc986;
            --warning-color: #ffc107;
            --danger-color: #cc0202;
        }
        
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding-bottom: 20px;
        }
        
        .mobile-header {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .form-title {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
            font-size: 1.5rem;
        }
        
        .form-floating label {
            color: #6c757d;
            padding: 0 12px;
        }
        
        .form-control, .form-select {
            height: 50px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 0 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 68, 209, 0.25);
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            margin-top: 15px;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background-color: #0b3bb7;
            transform: translateY(-2px);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }
        
        /* Animation for form elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        /* Delay animations for visual hierarchy */
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }
        
        /* QR preview section */
        .qr-preview {
            text-align: center;
            margin: 20px 0;
            display: none;
        }
        
        .qr-preview img {
            max-width: 150px;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .form-container {
                margin: 10px;
                padding: 15px;
            }
            
            .form-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h5 mb-0"><i class="fas fa-paw me-2"></i>Add New Animal</h1>
            </div>
            <a href="dashboard.php" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
    
    <!-- Main Form Container -->
    <div class="form-container">
        <h2 class="form-title">
            <i class="fas fa-plus-circle me-2"></i>New Animal Record
        </h2>
        
        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; unset($_SESSION['errors']); ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form action="add_animal_process.php" method="POST" id="animalForm" autocomplete="off">
        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; unset($_SESSION['errors']); ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <form action="add_animal_process.php" method="POST" id="animalForm" autocomplete="off">
            <!-- Animal Type -->
            <div class="form-group form-floating">
                <select class="form-select" name="type" id="type" required>
                    <option value="" selected disabled></option>
                    <option value="Chick">Chick</option>
                    <option value="Hen">Hen</option>
                    <option value="Rooster">Rooster</option>
                </select>
                <label for="type"><i class="fas fa-tag me-2"></i>Animal Type</label>
                <i class="fas fa-chevron-down input-icon"></i>
            </div>

            <div class="form-group form-floating">
                <select class="form-select" name="gender" id="gender" required>
                    <option value="" selected disabled></option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
                <label for="gender"><i class="fas fa-venus-mars me-2"></i>Gender</label>
                <i class="fas fa-chevron-down input-icon"></i>
            </div>
            <div id="genderError" class="alert alert-danger d-none" style="font-size:0.95em;"></div>
            
            <!-- Age -->
            <div class="form-group form-floating">
                <input type="number" class="form-control" name="age" id="age" required min="1">
                <label for="age"><i class="fas fa-calendar-day me-2"></i>Age in Days</label>
            </div>
            
            <!-- Breed Dropdown -->
            <div class="form-group form-floating">
                <select class="form-select" name="breed_dropdown" id="breed_dropdown" required>
                    <option value="" selected disabled>Select a Breed</option>
                    <option value="Asil">Asil</option>
                    <option value="American Gamefowl">American Gamefowl</option>
                    <option value="Kelso">Kelso</option>
                    <option value="Brown Red Gamefowl">Brown Red Gamefowl</option>
                    <option value="Peruvian Gamefowl">Peruvian Gamefowl</option>
                    <option value="Sweater Gamefowl">Sweater Gamefowl</option>
                    <option value="Hatch">Hatch</option>
                    <option value="Hatch Twist">Hatch Twist</option>
                    <option value="Whitehackle">Whitehackle</option>
                    <option value="Radio">Radio</option>
                </select>
                <label for="breed_dropdown"><i class="fas fa-dna me-2"></i>Breed</label>
            </div>

            <!-- Additional Nickname Textbox (hidden by default) -->
            <div class="form-group form-floating d-none" id="nickname_container">
                <input type="text" class="form-control" name="breed_nickname" id="breed_nickname">
                <label for="breed_nickname"><i class="fas fa-pencil-alt me-2"></i>Additional Nickname (optional)</label>
            </div>
            
            <!-- Mark -->
            <div class="form-group form-floating">
                <input type="text" class="form-control" name="mark" id="mark" required>
                <label for="mark"><i class="fas fa-tag me-2"></i>Mark/Identifier</label>
            </div>
            
            <!-- Breed Season -->
            <div class="form-group form-floating">
                <select class="form-select" name="breed_season" id="breed_season" required>
                    <option value="" selected disabled></option>
                    <option value="National">National</option>
                    <option value="Local">Local</option>
                    <option value="EarlyBird">EarlyBird</option>
                    <option value="LateBorn">LateBorn</option>
                </select>
                <label for="breed_season"><i class="fas fa-seedling me-2"></i>Breed Season</label>
                <i class="fas fa-chevron-down input-icon"></i>
            </div>
            

            
            <button type="submit" class="btn btn-submit">
                <i class="fas fa-save me-2"></i>Save Animal Record
            </button>
        </form>
        
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const breedDropdown = document.getElementById('breed_dropdown');
            const nicknameContainer = document.getElementById('nickname_container');

            breedDropdown.addEventListener('change', function() {
                if (this.value) {
                    nicknameContainer.classList.remove('d-none');
                } else {
                    nicknameContainer.classList.add('d-none');
                }
            });

            // Form submission with feedback and gender logic
            const form = document.getElementById('animalForm');
            const typeSelect = document.getElementById('type');
            const genderSelect = document.getElementById('gender');
            const genderError = document.getElementById('genderError');

            // Gender logic: adjust gender options based on type
            function updateGenderOptions() {
                const type = typeSelect.value;
                if (type === 'Rooster') {
                    genderSelect.value = 'male';
                    genderSelect.querySelector('option[value="female"]').disabled = true;
                    genderSelect.querySelector('option[value="male"]').disabled = false;
                } else if (type === 'Hen') {
                    genderSelect.value = 'female';
                    genderSelect.querySelector('option[value="male"]').disabled = true;
                    genderSelect.querySelector('option[value="female"]').disabled = false;
                } else {
                    genderSelect.querySelector('option[value="male"]').disabled = false;
                    genderSelect.querySelector('option[value="female"]').disabled = false;
                }
                genderError.classList.add('d-none');
                genderError.textContent = '';
            }

            typeSelect.addEventListener('change', updateGenderOptions);

            genderSelect.addEventListener('change', function() {
                const type = typeSelect.value;
                const gender = genderSelect.value;
                if ((type === 'Rooster' && gender === 'female') || (type === 'Hen' && gender === 'male')) {
                    genderError.classList.remove('d-none');
                    genderError.textContent =
                        'Invalid gender selection for ' + type + '. Please review basic poultry biology. This is not rocket science.';
                } else {
                    genderError.classList.add('d-none');
                    genderError.textContent = '';
                }
            });

            form.addEventListener('submit', function(e) {
                // Gender logic validation before submit
                const type = typeSelect.value;
                const gender = genderSelect.value;
                if ((type === 'Rooster' && gender === 'female') || (type === 'Hen' && gender === 'male')) {
                    e.preventDefault();
                    genderError.classList.remove('d-none');
                    genderError.textContent =
                        'You selected the wrong gender for a ' + type + '. Please try again and use your brain.';
                    genderSelect.focus();
                    return false;
                }
                // Allow normal form submission (no fake QR preview, no delay)
            });

            // Input validation
            document.querySelectorAll('input, select').forEach(input => {
                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                    }
                });

                input.addEventListener('blur', function() {
                    if (!this.checkValidity()) {
                        this.classList.add('is-invalid');
                    }
                });
            });
        });
    </script>
</body>
</html>