<?php
// vaccinational_tasks.php - Admin view for vaccination tasks assigned by Super Admin
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Only allow admins to access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo '<div class="container mt-5"><div class="alert alert-danger">ðŸš« You do not have permission to access this page.</div></div>';
    exit();
}

$admin_id = $_SESSION['user_id'];

// Fetch vaccination tasks assigned to this admin
$stmt = $pdo->prepare("SELECT vt.*, a.type AS animal_type, a.breed, a.mark, a.id AS animal_id, 
                              u.username AS assigned_by_name
                       FROM vaccination_tasks vt
                       JOIN animals a ON vt.animal_id = a.id
                       JOIN users u ON vt.assigned_by = u.id
                       WHERE vt.assigned_to = :admin_id
                       ORDER BY vt.id DESC");
$stmt->execute([':admin_id' => $admin_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also fetch admin messages (legacy tasks)
require_once 'includes/admin_messages.php';
$adminMessages = getAdminMessages($pdo, $admin_id, 'pending');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Tasks - FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', Arial, sans-serif; background: #f8fafc; }
        .task-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); margin-bottom: 24px; }
        .task-header { background: linear-gradient(90deg, #ffb300 0%, #e53935 100%); color: #fff; padding: 16px; border-top-left-radius: 16px; border-top-right-radius: 16px; font-weight: 600; }
        .task-body { padding: 18px; }
        .badge-chick { background: #fff8e1; color: #ffb300; }
        .badge-stag { background: #ffebee; color: #e53935; }
        .badge-rooster { background: #e3f2fd; color: #1e88e5; }
        .badge-hen { background: #e8f5e9; color: #43a047; }
        .status-badge { font-size: 0.9rem; padding: 6px 14px; border-radius: 12px; font-weight: 700; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .proof-img { max-width: 120px; border-radius: 8px; border: 1px solid #eee; }
        .urgent { border-left: 4px solid #e53935; }
        .qr-scanner-btn { background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); color: white; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-tasks me-2"></i> Vaccination Tasks</h3>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (empty($tasks) && empty($adminMessages)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-check-circle fa-2x mb-2" style="color:#43a047;"></i><br>
                No vaccination tasks or admin messages assigned to you yet.
            </div>
        <?php endif; ?>

        <!-- Current Vaccination Tasks -->
        <?php if (!empty($tasks)): ?>
            <h4 class="mb-3"><i class="fas fa-syringe me-2"></i> Current Vaccination Tasks</h4>
            <?php foreach ($tasks as $task): ?>
                <?php
                $badgeClass = 'badge-chick';
                switch (strtolower($task['animal_type'])) {
                    case 'stag': $badgeClass = 'badge-stag'; break;
                    case 'rooster': $badgeClass = 'badge-rooster'; break;
                    case 'hen': $badgeClass = 'badge-hen'; break;
                }
                
                // Determine if task is overdue
                $isOverdue = strtotime($task['due_date']) < time() && $task['status'] === 'pending';
                $statusClass = 'status-badge status-pending';
                if ($task['status'] === 'completed') {
                    $statusClass = 'status-badge status-completed';
                } elseif ($isOverdue) {
                    $statusClass = 'status-badge status-overdue';
                }
                ?>
                <div class="task-card <?= $isOverdue ? 'urgent' : '' ?>">
                    <div class="task-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-syringe me-2"></i> <?= htmlspecialchars($task['vaccine_type']) ?></span>
                        <span class="badge <?= $badgeClass ?> ms-2"><?= htmlspecialchars(ucfirst($task['animal_type'])) ?> #<?= $task['animal_id'] ?></span>
                    </div>
                    <div class="task-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>Animal Type:</strong> <?= htmlspecialchars($task['animal_type']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Vaccine Type:</strong> <?= htmlspecialchars($task['vaccine_type']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Due Date:</strong> <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                    <span class="badge <?= $statusClass ?> ms-2">
                                        <?= $isOverdue ? 'OVERDUE' : ucfirst($task['status']) ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <strong>Assigned By:</strong> <?= htmlspecialchars($task['assigned_by_name']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>Breed:</strong> <?= htmlspecialchars($task['breed']) ?> 
                                    <?php if (!empty($task['mark'])): ?>
                                        | <strong>Mark:</strong> <?= htmlspecialchars($task['mark']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($task['notes'])): ?>
                                    <div class="mb-2">
                                        <strong>Notes:</strong> <?= htmlspecialchars($task['notes']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($task['status'] === 'completed' && !empty($task['proof_image'])): ?>
                                    <div class="mb-2">
                                        <strong>Proof:</strong><br>
                                        <img src="<?= htmlspecialchars($task['proof_image']) ?>" class="proof-img mt-1" alt="Proof Image">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($task['status'] !== 'completed'): ?>
                            <div class="mt-3 p-3 bg-light rounded">
                                <h5 class="mb-3"><i class="fas fa-check-circle me-1"></i> Complete This Task</h5>
                                
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a href="scan_qr.php?task_id=<?= $task['id'] ?>" class="btn qr-scanner-btn w-100">
                                        <i class="fas fa-qrcode me-1"></i> Scan QR Code to Complete Task
                                    </a>
                                </div>
                                
                                <?php if ($isOverdue): ?>
                                    <div class="alert alert-warning mt-2 mb-0">
                                        <i class="fas fa-exclamation-triangle me-1"></i> This task is overdue! Please complete it as soon as possible.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                        <?php elseif ($task['status'] === 'completed'): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle me-1"></i> Completed on 
                                <?= !empty($task['completed_date']) ? date('M j, Y', strtotime($task['completed_date'])) : 'Unknown date' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add this to your existing JavaScript
    function handleTaskCompletion(form) {
        const formData = new FormData(form);
        
        fetch('complete_task.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.redirect) {
                    // Redirect to vaccination history page
                    window.location.href = data.redirect;
                } else {
                    // Show success message and reload
                    alert('Task completed successfully!');
                    window.location.reload();
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    // Update your form submission handlers to use this function
    document.querySelectorAll('form[action="complete_task.php"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleTaskCompletion(this);
        });
    });
    </script>
</body>
</html>