
<?php
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/admin_messages.php';

// Only allow super admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Access Denied</title><link rel="stylesheet" href="assets/css/style1.css"><style>.error-container{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;text-align:center;background-color:#f8f9fa;}.error-message{font-size:24px;color:#dc3545;margin-bottom:20px;}.back-button{padding:10px 20px;background-color:#007bff;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;}.back-button:hover{background-color:#0056b3;}</style></head><body><div class="error-container"><div class="error-message">🚫 You do not have permission to access this page.</div><button class="back-button" onclick="window.location.href=\'dashboard.php\'">Back to Dashboard</button></div></body></html>'; exit();
}

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
        (animal_id, task_type, task_details, recommended_vaccine, assigned_to, assigned_by, due_date, status, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([
        $animalId, 'Vaccination', $message, $recommendedVaccine, $receiverId, $_SESSION['user_id'], $dueDate, $message
    ]);
    $sendSuccess = true;
} else {
    $sendError = 'Please fill in all fields.';
}
}

// Only allow super admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Access Denied</title><link rel="stylesheet" href="assets/css/style1.css"><style>.error-container{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;text-align:center;background-color:#f8f9fa;}.error-message{font-size:24px;color:#dc3545;margin-bottom:20px;}.back-button{padding:10px 20px;background-color:#007bff;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;}.back-button:hover{background-color:#0056b3;}</style></head><body><div class="error-container"><div class="error-message">🚫 You do not have permission to access this page.</div><button class="back-button" onclick="window.location.href=\'dashboard.php\'">Back to Dashboard</button></div></body></html>'; exit();
}

// Get all completed vaccinations
$stmt = $pdo->prepare("
    SELECT t.*, a.type as animal_type, a.breed as animal_breed, 
           u.username as completed_by_name, u.email as completed_by_email
    FROM vaccination_tasks t
    JOIN animals a ON t.animal_id = a.id
    JOIN users u ON t.completed_by = u.id
    WHERE t.status = 'completed'
    ORDER BY t.completed_date DESC
");
$stmt->execute();
$completedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Vaccination Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #f8f9fa 0%, #e0f7fa 100%);
        }
        .card {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-radius: 16px;
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: background 0.2s;
        }
        .btn-dashboard:hover {
            background: #0056b3;
            color: #fff;
        }
        .form-label {
            font-weight: 500;
        }
        .table thead th {
            background: #e0f7fa;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f1f8e9;
        }
        .alert-success, .alert-danger {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Navbar include removed: file does not exist -->
    <a href="dashboard.php" class="btn btn-dashboard"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>

    <div class="container mt-4">
        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i> Send Vaccination Task</h5>
                <span class="badge bg-light text-success fs-6">Super Admin Panel</span>
            </div>
            <div class="card-body">
                <?php if ($sendSuccess): ?>
                    <div class="alert alert-success">Task sent!</div>
                <?php elseif ($sendError): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($sendError) ?></div>
                <?php endif; ?>
                <form method="post" id="sendTaskForm">
                    <div class="mb-3">
                        <label for="receiver_id" class="form-label">Select Admin</label>
                        <select name="receiver_id" id="receiver_id" class="form-select" required>
                            <option value="">Choose admin...</option>
                            <?php foreach ($adminList as $admin): ?>
                                <option value="<?= $admin['id'] ?>"><?= htmlspecialchars($admin['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="animal_type" class="form-label">Animal Type</label>
                        <select name="animal_type" id="animal_type" class="form-select" required onchange="filterAnimalsByType()">
                            <option value="">Select type...</option>
                            <option value="Chick">Chick</option>
                            <option value="Hen">Hen</option>
                            <option value="Rooster">Rooster</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="animal_id" class="form-label">Select Animal</label>
                        <select name="animal_id" id="animal_id" class="form-select" required onchange="suggestVaccines()">
                            <option value="">Select animal...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="recommended_vaccine" class="form-label">Recommended Vaccine</label>
                        <select id="recommended_vaccine" class="form-select" disabled>
                            <option value="">Select animal type first...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Task Message</label>
                        <textarea name="message" id="message" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="due_date" class="form-control">
                    </div>
                    <button type="submit" name="send_task" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Send Task
                    </button>
                </form>
                <script>
                // Filter animals by selected type (AJAX)
                function filterAnimalsByType() {
                    var type = document.getElementById('animal_type').value;
                    var animalSelect = document.getElementById('animal_id');
                    animalSelect.innerHTML = '<option value="">Loading...</option>';
                    if (!type) {
                        animalSelect.innerHTML = '<option value="">Select animal...</option>';
                        suggestVaccines();
                        return;
                    }
                    fetch('get_animals_by_type.php?type=' + encodeURIComponent(type))
                        .then(response => response.json())
                        .then(data => {
                            animalSelect.innerHTML = '<option value="">Select animal...</option>';
                            data.forEach(function(animal) {
                                var opt = document.createElement('option');
                                opt.value = animal.id;
                                opt.textContent = animal.type + ' - ' + animal.breed + ' (ID: ' + animal.id + ')';
                                animalSelect.appendChild(opt);
                            });
                            suggestVaccines();
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
                    // Vaccines for each type (hardcoded, ideally fetch from DB via AJAX)
                    var vaccines = {
                        'Chick': ["Marek's", "Newcastle + IB (Hitchner B1)", "Gumboro", "Gumboro booster", "Fowl Pox", "ND LaSota"],
                        'Hen': ["Fowl Cholera", "ND Clone/LaSota booster"],
                        'Rooster': ["Fowl Cholera"]
                    };
                    var list = vaccines[type] || [];
                    if (list.length === 0) {
                        vaccineSelect.innerHTML = '<option value="">No recommended vaccines</option>';
                        vaccineSelect.disabled = true;
                    } else {
                        vaccineSelect.disabled = false;
                        list.forEach(function(v){
                            var opt = document.createElement('option');
                            opt.value = v;
                            opt.textContent = v;
                            vaccineSelect.appendChild(opt);
                        });
                    }
                }
                </script>
            </div>
        </div>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                <h3 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i> Completed Vaccinations
                </h3>
                <span class="badge bg-light text-primary fs-6">Admin Reports</span>
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
                                    <th>Date/Time</th>
                                    <th>Proof</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedTasks as $task): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($task['animal_type']) ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($task['animal_breed']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($task['vaccination_type']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($task['completed_by_name']) ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($task['completed_by_email']) ?></small>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($task['completed_at'])) ?>
                                        <small class="text-muted d-block"><?= date('g:i A', strtotime($task['completed_at'])) ?></small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>