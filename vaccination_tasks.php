<?php
// vaccinational_tasks.php - Admin view for vaccination tasks assigned by Super Admin
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Only allow admins to access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo '<div class="container mt-5"><div class="alert alert-danger">🚫 You do not have permission to access this page.</div></div>';
    exit();
}

$admin_id = $_SESSION['user_id'];

// Fetch vaccination tasks assigned to this admin

// Also fetch admin messages (legacy tasks)
require_once 'includes/admin_messages.php';
$adminMessages = getAdminMessages($pdo, $admin_id, 'admin');

// Fetch vaccination tasks assigned to this admin (new system)
$stmt = $pdo->prepare("SELECT vt.*, a.type AS animal_type, a.breed, a.mark, a.id AS animal_id, u.username AS assigned_by
    FROM vaccination_tasks vt
    JOIN animals a ON vt.animal_id = a.id
    JOIN users u ON vt.assigned_by = u.id
    WHERE vt.assigned_to = :admin_id
    ORDER BY vt.due_date ASC");
$stmt->execute([':admin_id' => $admin_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    </style>
</head>
<body>
    <div class="container py-4">
        <h3 class="mb-4"><i class="fas fa-tasks me-2"></i> Vaccination Tasks</h3>
        <?php if (empty($tasks) && empty($adminMessages)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-check-circle fa-2x mb-2" style="color:#43a047;"></i><br>
                No vaccination tasks or admin messages assigned to you yet.
            </div>
        <?php endif; ?>

        <?php if (!empty($tasks)): ?>
            <?php foreach ($tasks as $task): ?>
                <?php
                $badgeClass = 'badge-chick';
                switch (strtolower($task['animal_type'])) {
                    case 'stag': $badgeClass = 'badge-stag'; break;
                    case 'rooster': $badgeClass = 'badge-rooster'; break;
                    case 'hen': $badgeClass = 'badge-hen'; break;
                }
                $statusClass = 'status-badge status-pending';
                if ($task['status'] === 'completed') $statusClass = 'status-badge status-completed';
                elseif ($task['status'] === 'overdue') $statusClass = 'status-badge status-overdue';
                ?>
                <div class="task-card">
                    <div class="task-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-syringe me-2"></i> <?= htmlspecialchars($task['vaccine_name']) ?></span>
                        <span class="badge <?= $badgeClass ?> ms-2"><?= htmlspecialchars(ucfirst($task['animal_type'])) ?> #<?= $task['animal_id'] ?></span>
                    </div>
                    <div class="task-body">
                        <div class="mb-2">
                            <strong>Due Date:</strong> <?= date('M j, Y', strtotime($task['due_date'])) ?>
                            <?php if ($task['status'] === 'overdue'): ?>
                                <span class="badge status-badge status-overdue ms-2">OVERDUE</span>
                            <?php elseif ($task['status'] === 'pending'): ?>
                                <span class="badge status-badge status-pending ms-2">Pending</span>
                            <?php elseif ($task['status'] === 'completed'): ?>
                                <span class="badge status-badge status-completed ms-2">Completed</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Assigned By:</strong> <?= htmlspecialchars($task['assigned_by']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Breed:</strong> <?= htmlspecialchars($task['breed']) ?> | <strong>Mark:</strong> <?= htmlspecialchars($task['mark']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Recommended Vaccine:</strong> <?= htmlspecialchars($task['recommended_vaccine']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Notes:</strong> <?= htmlspecialchars($task['notes']) ?>
                        </div>
                        <?php if ($task['status'] === 'pending'): ?>
                            <form action="complete_task.php" method="POST" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <div class="mb-2">
                                    <label for="proof_<?= $task['id'] ?>" class="form-label">Upload Proof (photo):</label>
                                    <input type="file" name="proof" id="proof_<?= $task['id'] ?>" class="form-control" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle me-1"></i> Mark as Completed
                                </button>
                            </form>
                        <?php elseif (!empty($task['proof_path'])): ?>
                            <div class="mb-2">
                                <strong>Proof:</strong><br>
                                <img src="<?= htmlspecialchars($task['proof_path']) ?>" class="proof-img" alt="Proof Image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($adminMessages)): ?>
            <h4 class="mt-5 mb-3"><i class="fas fa-envelope me-2"></i> Admin Messages / Legacy Tasks</h4>
            <?php foreach ($adminMessages as $msg): ?>
                <div class="task-card">
                    <div class="task-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($msg['task_type'] ?? 'Message') ?></span>
                        <span class="badge badge-chick ms-2">Message #<?= $msg['id'] ?></span>
                    </div>
                    <div class="task-body">
                        <div class="mb-2">
                            <strong>From:</strong> <?= htmlspecialchars($msg['sender_name'] ?? 'Super Admin') ?>
                        </div>
                        <div class="mb-2">
                            <strong>Due Date:</strong> <?= !empty($msg['due_date']) ? date('M j, Y', strtotime($msg['due_date'])) : 'N/A' ?>
                        </div>
                        <div class="mb-2">
                            <strong>Message:</strong> <?= htmlspecialchars($msg['message']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> <span class="badge <?= ($msg['status'] === 'completed') ? 'status-completed' : 'status-pending' ?>"><?= ucfirst($msg['status']) ?></span>
                        </div>
                        <?php if ($msg['status'] === 'pending'): ?>
                            <form action="complete_task.php" method="POST" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                <div class="mb-2">
                                    <label for="proof_msg_<?= $msg['id'] ?>" class="form-label">Upload Proof (photo):</label>
                                    <input type="file" name="proof" id="proof_msg_<?= $msg['id'] ?>" class="form-control" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle me-1"></i> Mark as Completed
                                </button>
                            </form>
                        <?php elseif (!empty($msg['proof'])): ?>
                            <div class="mb-2">
                                <strong>Proof:</strong><br>
                                <img src="<?= htmlspecialchars($msg['proof']) ?>" class="proof-img" alt="Proof Image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
    </div>
</body>
</html>
