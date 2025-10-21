<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Include necessary files
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/admin_messages.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only allow super admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Access Denied</title><link rel="stylesheet" href="assets/css/style1.css"><style>.error-container{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;text-align:center;background-color:#f8f9fa;}.error-message{font-size:24px;color:#dc3545;margin-bottom:20px;}.back-button{padding:10px 20px;background-color:#007bff;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;}.back-button:hover{background-color:#0056b3;}</style></head><body><div class="error-container"><div class="error-message">🚫 You do not have permission to access this page.</div><button class="back-button" onclick="window.location.href=\'dashboard.php\'">Back to Dashboard</button></div></body></html>';
    exit();
}

// Get current user ID from session
$userId = $_SESSION['user_id'] ?? null;

// Get list of admins
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'admin'");
$stmt->execute();
$adminList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle sending a new task
$sendSuccess = false;
$sendError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_task'])) {
    // Collect all form fields
    $receiverId = $_POST['receiver_id'] ?? '';
    $animalType = $_POST['animal_type'] ?? '';
    $animalId = $_POST['animal_id'] ?? '';
    $recommendedVaccine = $_POST['recommended_vaccine'] ?? '';
    $message = $_POST['message'] ?? '';
    $dueDate = $_POST['due_date'] ?? null;

    if ($receiverId && $animalType && $animalId && $recommendedVaccine && $message && $dueDate) {
        // Send admin message (legacy)
        sendAdminMessage($pdo, $_SESSION['user_id'], $receiverId, $message, 'Vaccination', $dueDate);

        // Insert into vaccination_tasks for admin dashboard linkage
        $stmt = $pdo->prepare("INSERT INTO vaccination_tasks 
            (animal_id, vaccine_type, assigned_to, assigned_by, due_date, status, notes) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $animalId,
            $recommendedVaccine,
            $receiverId,
            $_SESSION['user_id'],
            $dueDate,
            $message
        ]);
        $newTaskId = $pdo->lastInsertId();

        // Create a notification for the assigned admin
        $notificationMessage = "New vaccination task for '" . htmlspecialchars($recommendedVaccine) . "' assigned to you.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, related_type, related_id) VALUES (?, ?, 'vaccination_task', ?)");
        $stmt->execute([$receiverId, $notificationMessage, $newTaskId]);

        $sendSuccess = true;
    } else {
        $sendError = 'Please fill in all fields.';
    }
}

// Get completed admin messages
$completedMessages = getAdminMessages($pdo, $userId, 'completed');

// Get all completed vaccinations
$stmt = $pdo->prepare("
    SELECT t.*, a.type as animal_type, a.breed as animal_breed, 
           u.username as completed_by_name, u.email as completed_by_email
    FROM vaccination_tasks t
    JOIN animals a ON t.animal_id = a.id
    LEFT JOIN users u ON t.completed_by = u.id
    WHERE t.status = 'completed'
    ORDER BY t.completed_date DESC
");
$stmt->execute();
$completedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending tasks for notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count 
    FROM vaccination_tasks 
    WHERE status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute();
$pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Vaccination Reports</title>
    <link href="assets/images/6.0.png" rel="icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #f8f9fa 0%, #e0f7fa 100%);
            padding-top: 80px;
        }

        .card {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            margin-bottom: 24px;
        }

        .card-header {
            border-radius: 16px 16px 0 0;
        }

        .btn-dashboard {
            position: fixed;
            top: 24px;
            left: 24px;
            z-index: 100;
            background: #007bff;
            color: #fff;
            border-radius: 24px;
            padding: 10px 24px;
            font-size: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: background 0.2s;
        }

        .btn-dashboard:hover {
            background: #0056b3;
            color: #fff;
            text-decoration: none;
        }

        .form-label {
            font-weight: 500;
        }

        .table thead th {
            background: #e0f7fa;
            font-weight: 600;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f1f8e9;
        }

        .alert-success,
        .alert-danger {
            border-radius: 8px;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <a href="dashboard.php" class="btn btn-dashboard">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        <?php if ($pendingCount > 0): ?>
            <span class="notification-badge"><?= $pendingCount ?></span>
        <?php endif; ?>
    </a>

    <div class="container">
        <!-- Completed Admin Messages Section -->
        <?php if (!empty($completedMessages)): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i> Completed Admin Messages</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Message</th>
                                    <th>Completed By</th>
                                    <th>Completed Date</th>
                                    <th>Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedMessages as $message): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($message['message']) ?></td>
                                        <td><?= htmlspecialchars($message['completed_by_name'] ?? 'Unknown') ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($message['completed_date'])) ?></td>
                                        <td>
                                            <?php if ($message['proof']): ?>
                                                <a href="<?= htmlspecialchars($message['proof']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-image"></i> View Proof
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-warning">No proof</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Send Task Form -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i> Send Vaccination Task</h5>
                <span class="badge bg-light text-success fs-6">Super Admin Panel</span>
            </div>
            <div class="card-body">
                <?php if ($sendSuccess): ?>
                    <div class="alert alert-success">✅ Task sent successfully!</div>
                <?php elseif ($sendError): ?>
                    <div class="alert alert-danger">❌ <?= htmlspecialchars($sendError) ?></div>
                <?php endif; ?>

                <form method="post" id="sendTaskForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="receiver_id" class="form-label">Select Admin</label>
                            <select name="receiver_id" id="receiver_id" class="form-select" required>
                                <option value="">Choose admin...</option>
                                <?php foreach ($adminList as $admin): ?>
                                    <option value="<?= $admin['id'] ?>"><?= htmlspecialchars($admin['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="animal_type" class="form-label">Animal Type</label>
                            <select name="animal_type" id="animal_type" class="form-select" required onchange="filterAnimalsByType()">
                                <option value="">Select type...</option>
                                <option value="Chick">Chick</option>
                                <option value="Hen">Hen</option>
                                <option value="Rooster">Rooster</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="animal_id" class="form-label">Select Animal</label>
                            <select name="animal_id" id="animal_id" class="form-select" required>
                                <option value="">Select animal type first...</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="recommended_vaccine" class="form-label">Recommended Vaccine</label>
                            <select name="recommended_vaccine" id="recommended_vaccine" class="form-select" required>
                                <option value="">Select animal type first...</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Task Message</label>
                        <textarea name="message" id="message" class="form-control" rows="3" required placeholder="Enter task instructions..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="due_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>

                    <button type="submit" name="send_task" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i> Send Task
                    </button>
                </form>
            </div>
        </div>

        <!-- Completed Vaccinations Section -->
        <div class="card">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                <h3 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i> Completed Vaccinations
                </h3>
                <span class="badge bg-light text-primary fs-6"><?= count($completedTasks) ?> Completed</span>
            </div>
            <div class="card-body">
                <?php if (empty($completedTasks)): ?>
                    <p class="text-center text-muted">No completed vaccinations found</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Animal</th>
                                    <th>Vaccination</th>
                                    <th>Completed By</th>
                                    <th>Date Completed</th>
                                    <th>Proof</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="completed-tasks-table">
                                <?php foreach ($completedTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($task['animal_type']) ?></strong>
                                            <small class="text-muted d-block"><?= htmlspecialchars($task['animal_breed']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($task['vaccine_type']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($task['completed_by_name'] ?? 'Unknown') ?>
                                            <?php if ($task['completed_by_email']): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($task['completed_by_email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($task['completed_date'])) ?>
                                            <small class="text-muted d-block"><?= date('g:i A', strtotime($task['completed_date'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($task['proof_image']): ?>
                                                <a href="<?= htmlspecialchars($task['proof_image']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-image"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-warning">No proof</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_animal.php?id=<?= $task['animal_id'] ?>" class="btn btn-sm btn-info" title="View Animal">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="verify_vaccination.php?task_id=<?= $task['id'] ?>" class="btn btn-sm btn-success" title="Verify">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Filter animals by selected type (AJAX)
        function filterAnimalsByType() {
    var type = document.getElementById('animal_type').value;
    var animalSelect = document.getElementById('animal_id');
    var vaccineSelect = document.getElementById('recommended_vaccine');
    
    animalSelect.innerHTML = '<option value="">Loading...</option>';
    vaccineSelect.innerHTML = '<option value="">Select animal type first...</option>';
    vaccineSelect.disabled = true;
    
    if (!type) {
        animalSelect.innerHTML = '<option value="">Select animal type first...</option>';
        return;
    }
    
    // Real AJAX call to get_animals.php
    fetch('get_animals.php?type=' + encodeURIComponent(type))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                animalSelect.innerHTML = '<option value="">Select animal...</option>';
                data.animals.forEach(function(animal) {
                    var opt = document.createElement('option');
                    opt.value = animal.id;
                    var displayText = animal.type;
                    if (animal.breed) displayText += ' - ' + animal.breed;
                    if (animal.mark) displayText += ' (Mark: ' + animal.mark + ')';
                    displayText += ' (ID: ' + animal.id + ')';
                    opt.textContent = displayText;
                    opt.setAttribute('data-breed', animal.breed || '');
                    animalSelect.appendChild(opt);
                });
            } else {
                animalSelect.innerHTML = '<option value="">Error loading animals</option>';
                console.error('Error:', data.message);
            }
            suggestVaccines();
        })
        .catch(error => {
            animalSelect.innerHTML = '<option value="">Error loading animals</option>';
            console.error('Error fetching animals:', error);
        });
}

        // Suggest recommended vaccines for selected animal type
        function suggestVaccines() {
            var type = document.getElementById('animal_type').value;
            var vaccineSelect = document.getElementById('recommended_vaccine');
            vaccineSelect.innerHTML = '';

            if (!type) {
                vaccineSelect.innerHTML = '<option value="">Select animal type first...</option>';
                vaccineSelect.disabled = true;
                return;
            }

            // Vaccines for each type with detailed information
    var vaccines = {
        'Chick': [
            "Marek's Disease Vaccine - Day-old (hatchery)",
            "Newcastle Disease (B1 or Hitchner strain) - 7-10 days",
            "Infectious Bronchitis (IBV) - 7-10 days (often combined with Newcastle)",
            "Gumboro (IBD) - 14-21 days (booster may be needed)",
            "Fowl Pox - 4-6 weeks",
            "Avian Encephalomyelitis (AE) - 6 weeks (sometimes with fowlpox)"
        ],
        'Hen': [
            "Newcastle Disease (LaSota/Clone 30) - Every 2-3 months",
            "Infectious Bronchitis (Massachusetts/ND+IB combo) - Pre-lay booster",
            "Fowl Pox - If not vaccinated earlier, before laying",
            "Fowl Cholera (Pasteurella) - 10-12 weeks, boost as needed",
            "E. coli Vaccine (area-dependent, optional)",
            "Salmonella Vaccine (for food safety)"
        ],
        'Rooster': [
            "Newcastle Disease (LaSota/Clone 30) - Every 2-3 months",
            "Infectious Bronchitis (IBV) - Essential for breeders",
            "Fowl Pox - If not vaccinated as chick, before breeding",
            "Fowl Cholera - For endemic areas",
            "Avian Influenza (AI) - Where required/available"
        ]
    };

            var list = vaccines[type] || [];
            if (list.length === 0) {
                vaccineSelect.innerHTML = '<option value="">No recommended vaccines</option>';
                vaccineSelect.disabled = true;
            } else {
                vaccineSelect.disabled = false;
                list.forEach(function(v) {
                    var opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    vaccineSelect.appendChild(opt);
                });
            }
        }

        // Function to refresh completed tasks
        function refreshCompletedTasks() {
            fetch('get_completed_tasks.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTasksTable(data.tasks);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Function to update the tasks table
        function updateTasksTable(tasks) {
            const tbody = document.getElementById('completed-tasks-table');
            tbody.innerHTML = '';

            if (tasks.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No completed vaccinations found</td></tr>';
                return;
            }

            tasks.forEach(task => {
                const row = document.createElement('tr');

                // Format the completed date
                const completedDate = new Date(task.completed_date);
                const formattedDate = completedDate.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                const formattedTime = completedDate.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });

                row.innerHTML = `
            <td>
                <strong>${escapeHtml(task.animal_type)}</strong>
                <small class="text-muted d-block">${escapeHtml(task.animal_breed)}</small>
            </td>
            <td>${escapeHtml(task.vaccine_type)}</td>
            <td>
                ${escapeHtml(task.completed_by_name || 'Unknown')}
                ${task.completed_by_email ? `
                    <small class="text-muted d-block">${escapeHtml(task.completed_by_email)}</small>
                ` : ''}
            </td>
            <td>
                ${formattedDate}
                <small class="text-muted d-block">${formattedTime}</small>
            </td>
            <td>
                ${task.proof_image ? `
                    <a href="${escapeHtml(task.proof_image)}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-image"></i> View
                    </a>
                ` : '<span class="badge bg-warning">No proof</span>'}
            </td>
            <td>
                <a href="view_animal.php?id=${task.animal_id}" class="btn btn-sm btn-info" title="View Animal">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="verify_vaccination.php?task_id=${task.id}" class="btn btn-sm btn-success" title="Verify">
                    <i class="fas fa-check-circle"></i>
                </a>
            </td>
        `;

                tbody.appendChild(row);
            });
        }

        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Refresh tasks every 30 seconds
        setInterval(refreshCompletedTasks, 30000);

        // Also refresh when the page gains focus
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshCompletedTasks();
            }
        });

        // Set minimum date for due date to today
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>