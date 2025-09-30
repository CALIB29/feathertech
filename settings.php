<?php
// settings.php for FeatherTech Dashboard
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/db.php';

// Handle profile update (username, avatar, etc.)
$updateMsg = '';
$updateErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    if ($username && $username !== $_SESSION['username']) {
        $stmt = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
        $stmt->execute([$username, $_SESSION['user_id']]);
        $_SESSION['username'] = $username;
        $updateMsg = 'Username updated!';
    }
    // Avatar upload logic (optional)
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $avatarPath = 'uploads/avatars/' . basename($_FILES['avatar']['name']);
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarPath)) {
            $stmt = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
            $stmt->execute([$avatarPath, $_SESSION['user_id']]);
            $_SESSION['avatar_path'] = $avatarPath;
            $updateMsg = 'Avatar updated!';
        }
    }
    // Password change logic
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if ($current_password || $new_password || $confirm_password) {
        if (!$current_password || !$new_password || !$confirm_password) {
            $updateErr = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $updateErr = 'New passwords do not match.';
        } else {
            // Fetch current password hash
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($current_password, $row['password'])) {
                $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$newHash, $_SESSION['user_id']]);
                $updateMsg = 'Password changed successfully!';
            } else {
                $updateErr = 'Current password is incorrect.';
            }
        }
    }
}

// Fetch user info
$stmt = $pdo->prepare('SELECT username, avatar_path, role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; font-family: 'Poppins', sans-serif; }
        .settings-container { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 32px; }
        .settings-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #4a8fe7; margin-bottom: 12px; }
        .settings-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 18px; }
        .form-label { font-weight: 500; }
        .btn-primary { background: #4a8fe7; border: none; }
        .btn-primary:hover { background: #2e6ec8; }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="text-center mb-4">
            <img src="<?= htmlspecialchars($user['avatar_path'] ?? 'assets/images/solo_leveling.jpeg') ?>" class="settings-avatar" alt="Avatar" onerror="this.src='assets/images/solo_leveling.jpeg'">
            <div class="settings-title">Account Settings</div>
            <div class="text-muted mb-2">Role: <?= ucfirst($user['role'] ?? 'User') ?></div>
        </div>
        <?php if ($updateMsg): ?>
            <div class="alert alert-success"> <?= htmlspecialchars($updateMsg) ?> </div>
        <?php endif; ?>
        <?php if ($updateErr): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($updateErr) ?> </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Change Avatar</label>
                <input type="file" name="avatar" class="form-control">
            </div>
            <hr>
            <div class="mb-3 position-relative">
                <label class="form-label">Current Password</label>
                <div class="input-group">
                    <input type="password" name="current_password" class="form-control password-input" id="currentPassword" autocomplete="current-password">
                    <span class="input-group-text bg-white show-password-toggle" style="cursor:pointer;font-size:1.3rem;user-select:none;" data-target="currentPassword" title="Show/Hide Password">
                        🐔
                    </span>
                </div>
            </div>
            <div class="mb-3 position-relative">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" name="new_password" class="form-control password-input" id="newPassword" autocomplete="new-password">
                    <span class="input-group-text bg-white show-password-toggle" style="cursor:pointer;font-size:1.3rem;user-select:none;" data-target="newPassword" title="Show/Hide Password">
                        🐔
                    </span>
                </div>
            </div>
            <div class="mb-3 position-relative">
                <label class="form-label">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" class="form-control password-input" id="confirmPassword" autocomplete="new-password">
                    <span class="input-group-text bg-white show-password-toggle" style="cursor:pointer;font-size:1.3rem;user-select:none;" data-target="confirmPassword" title="Show/Hide Password">
                        🐔
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Save Changes</button>
        </form>
        <hr>
        <div class="text-center">
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        </div>
    </div>
</body>
<script>
// Show/hide password toggle with chicken emoji
document.querySelectorAll('.show-password-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function() {
        var targetId = this.getAttribute('data-target');
        var input = document.getElementById(targetId);
        if (input) {
            if (input.type === 'password') {
                input.type = 'text';
                this.style.opacity = 0.6;
            } else {
                input.type = 'password';
                this.style.opacity = 1;
            }
        }
    });
});
</script>
</html>
