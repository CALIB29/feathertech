<?php
// manage_admin.php - Super Admin Account Management
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';



// Only super admin can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    header('Location: dashboard.php');
    exit();
}


$successMsg = $errorMsg = '';

// Fetch all admins for selection
$adminList = [];
$stmt = $pdo->prepare("SELECT id, username, avatar_path FROM users WHERE role = 'admin'");
$stmt->execute();
$adminList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine selected admin (default: first admin)
$selectedAdminId = $_POST['selected_admin_id'] ?? ($adminList[0]['id'] ?? null);
$selectedAdmin = null;
if ($selectedAdminId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$selectedAdminId]);
    $selectedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Super admin can change selected admin's password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && $selectedAdmin) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $errorMsg = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = 'New passwords do not match.';
    } else {
        // Update password for selected admin (no need for current password)
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND role = ?');
        if ($stmt->execute([$newHash, $selectedAdminId, 'admin'])) {
            $successMsg = 'Password changed successfully.';
        } else {
            $errorMsg = 'Failed to update password.';
        }
    }
}

// Handle avatar upload for selected admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar']) && $selectedAdmin) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['avatar']['tmp_name'];
        $fileName = basename($_FILES['avatar']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) {
            $errorMsg = 'Invalid file type.';
        } else {
            $targetDir = 'uploads/avatars/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $newFileName = 'admin_' . $selectedAdminId . '_' . time() . '.' . $ext;
            $targetPath = $targetDir . $newFileName;
            if (move_uploaded_file($fileTmp, $targetPath)) {
                // Update avatar path in DB
                $stmt = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
                $stmt->execute([$targetPath, $selectedAdminId]);
                $successMsg = 'Avatar updated successfully.';
            } else {
                $errorMsg = 'Failed to upload avatar.';
            }
        }
    } else {
        $errorMsg = 'No file uploaded.';
    }
}

// Get selected admin's avatar
$avatarPath = $selectedAdmin['avatar_path'] ?? 'assets/images/solo_leveling.jpeg';
$username = $selectedAdmin['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admin Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', Arial, sans-serif; background: #f8fafc; }
        .container { max-width: 500px; margin: 40px auto; }
        .card { border-radius: 18px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .avatar-preview { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #ffc107; margin-bottom: 12px; }
        .form-label { font-weight: 500; }
        .btn-primary { background: linear-gradient(90deg, #ffb300 0%, #e53935 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(90deg, #e53935 0%, #ffb300 100%); }
        .back-btn { margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn btn-light back-btn"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        <div class="card p-4">
            <h4 class="mb-3"><i class="fas fa-user-shield me-2"></i> Manage Admin Accounts</h4>
            <form method="post" id="selectAdminForm" class="mb-3">
                <label class="form-label">Select Admin Account</label>
                <select name="selected_admin_id" class="form-select" onchange="document.getElementById('selectAdminForm').submit();">
                    <?php foreach ($adminList as $admin): ?>
                        <option value="<?= $admin['id'] ?>" <?= ($admin['id'] == $selectedAdminId ? 'selected' : '') ?>><?= htmlspecialchars($admin['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <!-- Toasts for success/error -->
            <div aria-live="polite" aria-atomic="true" class="position-relative">
                <div id="toast-container" class="position-absolute top-0 end-0 p-3" style="z-index: 9999">
                    <?php if ($successMsg): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($successMsg) ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    <?php elseif ($errorMsg): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($errorMsg) ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center mb-3">
                <img id="avatarPreview" src="<?= htmlspecialchars($avatarPath) ?>" class="avatar-preview" alt="Avatar">
                <form method="post" enctype="multipart/form-data" class="mt-2">
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" class="form-control mb-2" style="max-width:220px;display:inline-block;">
                    <button type="submit" name="upload_avatar" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i> Upload Avatar</button>
                </form>
                <div id="avatarPreviewText" class="text-muted small"></div>
            </div>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="Admin" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Password <span class="text-muted small">(Visible for super admin only)</span></label>
                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($selectedAdmin['password'] ?? '') ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary w-100">
                    <i class="fas fa-key me-1"></i> Change Admin Password
                </button>
            </form>
        </div>
    </div>
<script>
// Avatar preview
document.getElementById('avatarInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('avatarPreview').src = ev.target.result;
            document.getElementById('avatarPreviewText').textContent = 'Previewing: ' + file.name;
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('avatarPreview').src = "<?= htmlspecialchars($avatarPath) ?>";
        document.getElementById('avatarPreviewText').textContent = '';
    }
});

// Bootstrap toast auto-hide
document.addEventListener('DOMContentLoaded', function() {
    var toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.forEach(function(toastEl) {
        var toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    });
});
</script>
</body>
</html>
