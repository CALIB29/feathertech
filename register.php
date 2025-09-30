<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
include 'includes/db.php';
include 'includes/auth.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not authenticated
    header("Location: index.php");
    exit();
}

// Role-based access control: Only super admin can access this page
if ($_SESSION['role'] !== 'super admin') {
    // Deny access and show a styled error message with a button
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <link rel="stylesheet" href="assets/css/style1.css">
        <style>
            .error-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100vh;
                text-align: center;
                background-color: #f8f9fa;
            }
            .error-message {
                font-size: 24px;
                color: #dc3545;
                margin-bottom: 20px;
            }
            .back-button {
                padding: 10px 20px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
            .back-button:hover {
                background-color: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-message">🚫 You do not have permission to access this page.</div>
            <button class="back-button" onclick="window.location.href=\'dashboard.php\'">Back to Dashboard</button>
        </div>
    </body>
    </html>
    ';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form input data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = strtolower($_POST['role']); // Normalize role to lowercase

    // Initialize errors array
    $errors = [];

    // reCAPTCHA validation
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
if (empty($recaptcha_response)) {
    $errors[] = 'Please complete the reCAPTCHA.';
} else {
    $secret_key = '6LcOp80rAAAAAKKxHX6edoALcSpVdg-qw653VICu';
    $url = 'https://www.google.com/recaptcha/api/siteverify'; // Correct URL
    
    $data = [
        'secret' => $secret_key,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response, true);

    if (!$result['success']) {
        $errors[] = 'reCAPTCHA verification failed. Please try again.';
        // For debugging, you can log the reCAPTCHA response:
        error_log("reCAPTCHA verification failed. Response: " . print_r($result, true));
    }
}

    // Existing validations
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $errors[] = 'All fields are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    // Match role values with normalized lowercase
    if (!in_array($role, ['superadmin', 'admin', 'user'])) {
        $errors[] = 'Invalid role selected.';
    }

    // Avatar upload handling
    $avatarPath = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            $errors[] = 'Only JPG, PNG, and GIF images are allowed for avatar.';
        } elseif ($_FILES['avatar']['size'] > $maxFileSize) {
            $errors[] = 'Avatar image must be less than 2MB.';
        } else {
            // Create uploads directory if it doesn't exist
            if (!file_exists('uploads/avatars')) {
                mkdir('uploads/avatars', 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . uniqid() . '.' . $extension;
            $uploadPath = 'uploads/avatars/' . $filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                $avatarPath = $uploadPath;
            } else {
                $errors[] = 'Failed to upload avatar image.';
            }
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        // ... existing user check code ...

        // Hash password and insert user with avatar
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // After successful avatar upload
$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, avatar_path) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, $email, $hashed_password, $role, $avatarPath]);

// Also set in session if you want to show immediately
$_SESSION['avatar_path'] = $avatarPath;

        $_SESSION['success'] = 'Registration successful!';
        header('Location: register.php');
        exit();
    } // After successful registration

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register | FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        :root {
            --primary-color: #0d44d1;
            --secondary-color: #15dfe6;
            --success-color: #28a745;
            --error-color: #dc3545;
        }
        
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .auth-header {
            background: var(--primary-color);
            color: white;
            padding: 20px 0;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .auth-container {
            max-width: 500px;
            margin: 30px auto;
            padding: 0 15px;
            flex: 1;
            width: 100%;
        }
        
        .auth-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .auth-title {
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
        
        .form-control {
            height: 50px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 0 15px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 68, 209, 0.25);
        }
        
        .btn-auth {
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
        
        .btn-auth:hover {
            background-color: #0b3bb7;
            transform: translateY(-2px);
        }
        
        .btn-auth:active {
            transform: translateY(0);
        }
        
        .auth-footer {
            text-align: center;
            color: #6c757d;
            margin-top: 20px;
        }
        
        .auth-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }
        
        .password-toggle {
            cursor: pointer;
            z-index: 5;
        }
        
        /* Error/Success messages */
        .alert-message {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid transparent;
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border-left-color: var(--error-color);
            color: var(--error-color);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left-color: var(--success-color);
            color: var(--success-color);
        }
        
        /* Role selector styling */
        .role-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .role-option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .role-option:hover {
            border-color: var(--primary-color);
        }
        
        .role-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .role-icon {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: var(--primary-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .auth-card {
                padding: 20px;
            }
            
            .auth-title {
                font-size: 1.3rem;
            }
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

        /* Avatar Upload Styles */
.avatar-upload-container {
    text-align: center;
}

.avatar-preview {
    width: 120px;
    height: 120px;
    margin: 0 auto 10px;
    border: 3px solid var(--primary-color);
    border-radius: 50%;
    overflow: hidden;
    background-color: #f8f9fa;
}

.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
    </style>
</head>
<body>
    <!-- Header -->
    <header class="auth-header">
        <div class="container">
            <h1><i class="fas fa-user-plus me-2"></i>Create Account</h1>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="auth-container">
        <div class="auth-card">
            <h2 class="auth-title">Register New User</h2>
            
            <!-- Error Messages -->
            <?php if (isset($_SESSION['errors'])): ?>
                <div class="alert-message alert-error">
                    <h5><i class="fas fa-exclamation-circle me-2"></i>Registration Errors</h5>
                    <ul class="mb-0">
                        <?php foreach ($_SESSION['errors'] as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php unset($_SESSION['errors']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-message alert-success">
                    <h5><i class="fas fa-check-circle me-2"></i>Success!</h5>
                    <p class="mb-0"><?= htmlspecialchars($_SESSION['success']) ?></p>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <!-- Username -->
                <div class="form-group form-floating">
                    <input type="text" class="form-control" name="username" id="username" required 
                           placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>
                
                <!-- Email -->
                <div class="form-group form-floating">
                    <input type="email" class="form-control" name="email" id="email" required 
                           placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                </div>
                
                <!-- Password -->
                <div class="form-group form-floating position-relative">
                    <input type="password" class="form-control" name="password" id="password" required 
                           placeholder="Password" minlength="8">
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    <i class="fas fa-eye password-toggle input-icon" 
                       onclick="togglePassword('password', this)"></i>
                </div>
                
                <!-- Confirm Password -->
                <div class="form-group form-floating position-relative">
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" 
                           required placeholder="Confirm Password" minlength="8">
                    <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                    <i class="fas fa-eye password-toggle input-icon" 
                       onclick="togglePassword('confirm_password', this)"></i>
                </div>
                
                <!-- Role Selection -->
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user-tag me-2"></i>Select Role</label>
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="admin"
                                   <?= ($_POST['role'] ?? '') === 'admin' ? 'checked' : '' ?>>
                            <i class="fas fa-user-shield role-icon"></i>
                            <span>Administrator</span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="superadmin"
                                   <?= ($_POST['role'] ?? '') === 'superadmin' ? 'checked' : '' ?>>
                            <i class="fas fa-crown role-icon"></i>
                            <span>Super Administrator</span>
                        </label>
                    </div>
                </div>
                
                <!-- reCAPTCHA -->
                <div class="form-group">
                    <div class="g-recaptcha" data-sitekey="6LcOp80rAAAAACyEw1mLRUOvLWAomOKNsbCv9UWM"></div>
                </div>
                
                <button type="submit" class="btn btn-auth">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
        </div>
        
        <div class="auth-footer">
            <a href="dashboard.php" class="auth-link"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // Toggle password visibility
        function togglePassword(id, icon) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Form submission with loading state
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Account...';
            submitBtn.disabled = true;
        });
        
        // Input validation
        document.querySelectorAll('input').forEach(input => {
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
        
        // Password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePasswordMatch() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
                confirmPassword.classList.add('is-invalid');
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
            }
        }
        
        password.addEventListener('input', validatePasswordMatch);
        confirmPassword.addEventListener('input', validatePasswordMatch);
    </script>
</body>
</html>