<?php
include 'includes/db.php';
include 'includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_secret_user'])) {
    header("Location: index.php");
    exit();
}

// Fetch archived animals
$stmt = $pdo->query("SELECT * FROM animal_archive ORDER BY deleted_at DESC");
$archivedAnimals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format age (from dashboard.php)
function formatAge($days) {
    return floor($days / 30) . " month(s)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Archived Records - FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d44d1;
            --primary-light: #3d6bdf;
            --secondary-color: #15dfe6;
            --success-color: #4bc986;
            --warning-color: #ffc107;
            --danger-color: #cc0202;
            --card-shadow: 0 6px 15px rgba(0,0,0,0.1);
            --3d-shadow: 0 8px 25px -5px rgba(0,0,0,0.2);
        }
        
        /* 3D Effects */
        .card-3d {
            transform-style: preserve-3d;
            transition: all 0.5s ease;
            perspective: 1000px;
        }
        
        .card-3d:hover {
            transform: translateY(-5px) rotateX(5deg);
            box-shadow: var(--3d-shadow);
        }
        
        .btn-3d {
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-3d:active {
            transform: translateY(2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Mobile-first base styles */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ed 100%);
            color: #333;
            min-height: 100vh;
            padding-bottom: 80px;
        }
        
        /* App Header */
        .app-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .app-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .app-logo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
        }
        
        /* Main Content */
        .app-container {
            padding: 15px;
        }
        
        /* Archive Grid - Modern Layout */
        .archive-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .archive-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .archive-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--3d-shadow);
        }
        
        .archive-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 12px 15px;
            font-weight: 500;
        }
        
        .archive-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 15px;
        }
        
        .archive-detail {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-ready {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-not-ready {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            padding: 0 15px 15px;
        }
        
        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        
        .restore-btn {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        /* Bottom Navigation - 3D Effect */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -8px 25px -5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            z-index: 1000;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #666;
            text-decoration: none;
            font-size: 0.8rem;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }
        
        .nav-item.active {
            color: var(--primary-color);
            transform: translateY(-8px);
        }
        
        .nav-item i {
            font-size: 1.4rem;
            margin-bottom: 3px;
            transition: all 0.3s ease;
        }
        
        .nav-item.active i {
            transform: scale(1.2);
            text-shadow: 0 4px 8px rgba(13, 68, 209, 0.2);
        }
        
        /* Search Bar */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 20px;
            height: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: #ced4da;
            margin-bottom: 15px;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .archive-grid-container {
                grid-template-columns: 1fr;
            }
            
            .archive-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- App Header -->
    <div class="app-header">
        <div class="app-title">
            <img src="/assets/images/FeatherTech.jpg" alt="Logo" class="app-logo">
            Archived Records
        </div>
        <button class="btn btn-sm btn-light" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <!-- Main Content -->
    <div class="app-container">
        <!-- Search Bar -->
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="archiveSearch" class="form-control" placeholder="Search archived records...">
        </div>
        
        <!-- Archive Grid -->
        <?php if (empty($archivedAnimals)): ?>
            <div class="empty-state">
                <i class="fas fa-archive fa-3x mb-3"></i>
                <h5>No Archived Records</h5>
                <p>There are currently no archived animal records</p>
                <a href="dashboard.php" class="btn btn-primary btn-3d mt-3">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="archive-grid-container" id="archiveContainer">
                <?php foreach ($archivedAnimals as $animal): ?>
                <div class="archive-card card-3d">
                    <div class="archive-header">
                        <?= htmlspecialchars($animal['type']) ?> #<?= $animal['id'] ?>
                    </div>
                    <div class="archive-grid">
                        <div class="archive-detail">
                            <span class="detail-label">Age at Archive</span>
                            <span class="detail-value"><?= formatAge($animal['age']) ?></span>
                        </div>
                        <div class="archive-detail">
                            <span class="detail-label">Breed</span>
                            <td><?= htmlspecialchars($animal['breed']) ?></td>
                        </div>
                        <div class="archive-detail">
                            <span class="detail-label">Mark</span>
                            <span class="detail-value"><?= htmlspecialchars($animal['mark']) ?></span>
                        </div>
                        <div class="archive-detail">
                            <span class="detail-label">Status</span>
                            <span class="status-badge <?= $animal['status'] === 'Not Yet Ready' ? 'status-not-ready' : 'status-ready' ?>">
                                <?= htmlspecialchars($animal['status']) ?>
                            </span>
                        </div>
                        <div class="archive-detail">
                            <span class="detail-label">Archived On</span>
                            <span class="detail-value"><?= date('M j, Y', strtotime($animal['deleted_at'])) ?></span>
                        </div>
                        <div class="archive-detail">
                            <span class="detail-label">Archived At</span>
                            <span class="detail-value"><?= date('g:i a', strtotime($animal['deleted_at'])) ?></span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button class="action-btn restore-btn btn-3d" 
                                data-id="<?= $animal['id'] ?>"
                                data-bs-toggle="modal" 
                                data-bs-target="#restoreModal">
                            <i class="fas fa-undo me-1"></i> Restore
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="add_animal.php" class="nav-item">
            <i class="fas fa-plus"></i>
            <span>Add</span>
        </a>
        <a href="#" class="nav-item active">
            <i class="fas fa-archive"></i>
            <span>Archive</span>
        </a>
    </div>
    
    <!-- Restore Confirmation Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Restoration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to restore this record to active status?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-success" id="confirmRestore">Restore Record</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('archiveSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const archiveCards = document.querySelectorAll('.archive-card');
            
            archiveCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Restore functionality
        document.querySelectorAll('.restore-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('confirmRestore').href = `restore_animal.php?id=${id}`;
            });
        });
        
        // Initialize with all cards shown
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight current page in navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                if (item.href === window.location.href) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>