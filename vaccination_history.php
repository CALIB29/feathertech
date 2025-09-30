<?php
// vaccination_history.php: Show vaccination history for a specific animal
include 'includes/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=invalid_id");
    exit();
}

$animalId = (int)$_GET['id'];

// Fetch animal info
$stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
$stmt->execute([$animalId]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$animal) {
    header("Location: dashboard.php?error=animal_not_found");
    exit();
}

// Fetch vaccination history (from vaccination_history table)
$history_stmt = $pdo->prepare("SELECT * FROM vaccination_history WHERE animal_id = ? ORDER BY vaccination_date DESC");
$history_stmt->execute([$animalId]);
$vaccination_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination History | <?= htmlspecialchars($animal['type']) ?> #<?= $animalId ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sticky header for animal info */
        .animal-header.sticky {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            box-shadow: 0 2px 8px #0d44d10a;
            padding-top: 12px;
            padding-bottom: 12px;
            margin-bottom: 0;
        }
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
            align-items: center;
            justify-content: space-between;
        }
        .filter-bar input[type="date"], .filter-bar select, .filter-bar input[type="text"] {
            border-radius: 6px;
            border: 1px solid #b6c6f5;
            padding: 4px 8px;
            font-size: 0.98em;
        }
        .export-btns {
            display: flex;
            gap: 8px;
        }
        .export-btns button {
            border: none;
            background: linear-gradient(90deg, #0d44d1 60%, #4f8cff 100%);
            color: #fff;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 500;
            font-size: 0.98em;
            cursor: pointer;
            transition: background 0.2s;
        }
        .export-btns button:hover {
            background: linear-gradient(90deg, #4f8cff 60%, #0d44d1 100%);
        }
        .collapse-toggle {
            background: none;
            border: none;
            color: #0d44d1;
            font-weight: 600;
            font-size: 1.01em;
            cursor: pointer;
            margin-bottom: 8px;
        }
        body { background: linear-gradient(120deg, #e0e7ff 0%, #f8fafc 100%); font-family: 'Poppins', sans-serif; }
        .container { max-width: 650px; margin: 32px auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px rgba(13,68,209,0.08); padding: 32px 24px 28px 24px; }
        .animal-header { text-align: center; margin-bottom: 32px; }
        .animal-header h2 { font-size: 2rem; font-weight: 700; margin-bottom: 0; color: #0d44d1; letter-spacing: 0.5px; }
        .animal-header .badge { font-size: 1.08rem; margin-top: 10px; background: #e0e7ff; color: #0d44d1; border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        .tracker-section { margin-bottom: 32px; }
        .vaccination-tracker {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 0 auto 18px auto;
            flex-wrap: wrap;
        }
        .tracker-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            min-width: 70px;
        }
        .tracker-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e0e7ff;
            border: 2.5px solid #b6c6f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #0d44d1;
            font-weight: 600;
            transition: background 0.4s, border 0.4s, color 0.4s;
            z-index: 2;
        }
        .tracker-step.completed .tracker-circle {
            background: linear-gradient(135deg, #0d44d1 60%, #4f8cff 100%);
            color: #fff;
            border-color: #0d44d1;
            animation: pop 0.5s cubic-bezier(.68,-0.55,.27,1.55);
        }
        .tracker-label {
            margin-top: 7px;
            font-size: 0.98rem;
            color: #0d44d1;
            font-weight: 500;
            text-align: center;
            min-width: 60px;
        }
        .tracker-step.completed .tracker-label {
            color: #0d44d1;
            font-weight: 700;
        }
        .tracker-bar {
            position: absolute;
            top: 18px;
            left: 38px;
            width: 44px;
            height: 5px;
            background: #b6c6f5;
            z-index: 1;
            border-radius: 3px;
            transition: background 0.4s;
        }
        .tracker-step.completed .tracker-bar {
            background: linear-gradient(90deg, #0d44d1 60%, #4f8cff 100%);
        }
        @media (max-width: 600px) {
            .container { padding: 16px 4px 18px 4px; }
            .animal-header h2 { font-size: 1.2rem; }
            .animal-header .badge { font-size: 0.95rem; padding: 6px 10px; }
            .tracker-label { font-size: 0.89rem; min-width: 40px; }
            .tracker-circle { width: 30px; height: 30px; font-size: 1.05rem; }
            .tracker-bar { width: 28px; left: 30px; top: 14px; }
        }
        @keyframes pop {
            0% { transform: scale(1); }
            60% { transform: scale(1.25); }
            100% { transform: scale(1); }
        }
        .history-list { margin-top: 10px; }
        .history-list .list-group-item {
            border-radius: 10px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px #0d44d10a;
            background: #f6f8ff;
            border: none;
            transition: box-shadow 0.2s;
        }
        .history-list .list-group-item:hover {
            box-shadow: 0 4px 16px #0d44d11a;
        }
        .history-list .vaccine-title { font-weight: 600; color: #0d44d1; font-size: 1.08rem; }
        .history-list .vaccine-date { color: #888; font-size: 0.97em; }
        .history-list .notes { color: #555; font-size: 0.97em; }
        .history-list img { max-width: 70px; max-height: 70px; border-radius: 8px; border: 1px solid #e0e7ff; margin-left: 10px; box-shadow: 0 1px 4px #0d44d110; }
        .no-history { text-align: center; color: #888; margin-top: 30px; }
        .back-link { display: block; text-align: center; margin-top: 30px; color: #0d44d1; text-decoration: none; font-weight: 600; letter-spacing: 0.2px; }
        .back-link:hover { text-decoration: underline; color: #4f8cff; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Last Vaccination Summary Card -->
        <?php if (!empty($vaccination_history)): ?>
            <?php $last = $vaccination_history[0]; ?>
            <div class="alert alert-info mb-4" style="font-size:1.01em; box-shadow:0 2px 8px #0d44d10a;">
                <strong>Last Vaccination:</strong> <span class="text-primary"><?= htmlspecialchars($last['vaccine_name'] ?? $last['vaccination_type'] ?? 'N/A') ?></span>
                on <b><?= htmlspecialchars($last['vaccination_date'] ?? $last['created_at'] ?? 'N/A') ?></b>
                <?php if (!empty($last['notes'])): ?>
                    <br><span class="text-muted">Notes: <?= htmlspecialchars($last['notes']) ?></span>
                <?php endif; ?>
                <?php if (!empty($last['administered_by'])): ?>
                    <br><span class="text-muted">Administered by: <?= htmlspecialchars($last['administered_by']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="animal-header sticky" id="animalHeader">
            <h2><i class="fas fa-shield-virus me-2"></i>Vaccination History</h2>
            <div class="badge">ID: <?= $animalId ?> &nbsp;|&nbsp; <?= htmlspecialchars($animal['type']) ?> (<?= htmlspecialchars($animal['breed']) ?>)</div>
        </div>

        <!-- Filter/Search/Export Bar -->
        <form class="filter-bar" id="filterForm" onsubmit="return false;">
            <div>
                <input type="date" id="filterStart" name="start" placeholder="From">
                <input type="date" id="filterEnd" name="end" placeholder="To">
                <select id="filterVaccine">
                    <option value="">All Vaccines</option>
                    <?php
                        $vaccine_types = array_unique(array_map(function($rec){
                            return $rec['vaccine_name'] ?? $rec['vaccination_type'] ?? 'N/A';
                        }, $vaccination_history));
                        foreach ($vaccine_types as $vtype):
                    ?>
                        <option value="<?= htmlspecialchars($vtype) ?>"><?= htmlspecialchars($vtype) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="filterSearch" placeholder="Search notes or vaccine...">
            </div>
            <div class="export-btns">
                <button type="button" onclick="exportCSV()"><i class="fas fa-file-csv me-1"></i>Export CSV</button>
                <button type="button" onclick="exportPDF()"><i class="fas fa-file-pdf me-1"></i>Export PDF</button>
            </div>
        </form>

        <!-- Vaccination Tracker Animation -->
        <div class="tracker-section">
            <?php
                // Build unique vaccine steps in chronological order (oldest first)
                $tracker_steps = [];
                foreach (array_reverse($vaccination_history) as $rec) {
                    $label = $rec['vaccine_name'] ?? $rec['vaccination_type'] ?? 'N/A';
                    if (!in_array($label, $tracker_steps)) {
                        $tracker_steps[] = $label;
                    }
                }
                $tracker_steps = array_reverse($tracker_steps); // so left-to-right is oldest-to-newest
                $completed_count = count($tracker_steps);
            ?>
            <?php if ($completed_count > 0): ?>
                <div class="vaccination-tracker" id="vaccination-tracker">
                    <?php foreach ($tracker_steps as $i => $step): ?>
                        <div class="tracker-step completed" style="animation-delay: <?= ($i*0.15) ?>s;">
                            <div class="tracker-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="tracker-label"><?= htmlspecialchars($step) ?></div>
                            <?php if ($i < $completed_count-1): ?>
                                <div class="tracker-bar"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mb-2" style="color:#0d44d1;font-size:1.01rem;font-weight:500;">
                    <i class="fas fa-syringe me-1"></i><?= $completed_count ?> vaccine<?= $completed_count>1?'s':'' ?> completed
                </div>
            <?php else: ?>
                <div class="vaccination-tracker text-center" style="color:#888;">
                    <i class="fas fa-syringe"></i> No vaccinations recorded yet.
                </div>
            <?php endif; ?>
        </div>

        <button class="collapse-toggle" id="toggleHistory" type="button" onclick="toggleHistoryList()">
            <span id="toggleHistoryIcon">&#9660;</span> Vaccination History
        </button>
        <div class="history-list" id="historyList">
            <?php if (!empty($vaccination_history)): ?>
                <ul class="list-group" id="historyListUl">
                    <?php foreach ($vaccination_history as $record): ?>
                        <li class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center history-item"
                            data-date="<?= htmlspecialchars($record['vaccination_date'] ?? $record['created_at'] ?? '') ?>"
                            data-vaccine="<?= htmlspecialchars($record['vaccine_name'] ?? $record['vaccination_type'] ?? 'N/A') ?>"
                            data-notes="<?= htmlspecialchars($record['notes'] ?? '') ?>">
                            <div>
                                <span class="vaccine-title"><i class="fas fa-syringe me-1"></i> <?= htmlspecialchars($record['vaccine_name'] ?? $record['vaccination_type'] ?? 'N/A') ?></span><br>
                                <span class="vaccine-date">Date: <?= htmlspecialchars($record['vaccination_date'] ?? $record['created_at'] ?? 'N/A') ?></span>
                                <?php if (!empty($record['notes'])): ?>
                                    <br><span class="notes">Notes: <?= htmlspecialchars($record['notes']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($record['proof_image']) && file_exists($record['proof_image'])): ?>
                                <img src="<?= htmlspecialchars($record['proof_image']) ?>" alt="Proof" title="Proof Image">
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="no-history">
                    <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                    No vaccination records found for this animal.
                </div>
            <?php endif; ?>
        </div>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// Animate tracker steps in sequence
window.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.tracker-step.completed');
    steps.forEach((step, i) => {
        step.style.opacity = 0;
        setTimeout(() => {
            step.style.opacity = 1;
            step.classList.add('pop');
        }, 200 + i*180);
    });
});

// Collapsible history
function toggleHistoryList() {
    const list = document.getElementById('historyList');
    const icon = document.getElementById('toggleHistoryIcon');
    if (list.style.display === 'none') {
        list.style.display = '';
        icon.innerHTML = '&#9660;';
    } else {
        list.style.display = 'none';
        icon.innerHTML = '&#9654;';
    }
}

// Filtering logic
function filterHistory() {
    const start = document.getElementById('filterStart').value;
    const end = document.getElementById('filterEnd').value;
    const vaccine = document.getElementById('filterVaccine').value.toLowerCase();
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const items = document.querySelectorAll('.history-item');
    items.forEach(item => {
        let show = true;
        const date = item.getAttribute('data-date');
        const vtype = item.getAttribute('data-vaccine').toLowerCase();
        const notes = item.getAttribute('data-notes').toLowerCase();
        if (start && date < start) show = false;
        if (end && date > end) show = false;
        if (vaccine && vtype !== vaccine) show = false;
        if (search && !(vtype.includes(search) || notes.includes(search))) show = false;
        item.style.display = show ? '' : 'none';
    });
}
document.getElementById('filterStart').addEventListener('change', filterHistory);
document.getElementById('filterEnd').addEventListener('change', filterHistory);
document.getElementById('filterVaccine').addEventListener('change', filterHistory);
document.getElementById('filterSearch').addEventListener('input', filterHistory);

// Export CSV
function exportCSV() {
    let csv = 'Vaccine,Date,Notes\n';
    document.querySelectorAll('.history-item').forEach(item => {
        if (item.style.display === 'none') return;
        const v = item.getAttribute('data-vaccine').replace(/\n|\r|,/g, ' ');
        const d = item.getAttribute('data-date');
        const n = item.getAttribute('data-notes').replace(/\n|\r|,/g, ' ');
        csv += `"${v}","${d}","${n}"\n`;
    });
    const blob = new Blob([csv], {type: 'text/csv'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'vaccination_history_animal_<?= $animalId ?>.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Export PDF (simple table)
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(14);
    doc.text('Vaccination History - Animal #<?= $animalId ?>', 10, 12);
    let y = 22;
    doc.setFontSize(11);
    doc.text('Vaccine', 10, y);
    doc.text('Date', 70, y);
    doc.text('Notes', 120, y);
    y += 6;
    doc.setLineWidth(0.1);
    doc.line(10, y, 200, y);
    y += 4;
    document.querySelectorAll('.history-item').forEach(item => {
        if (item.style.display === 'none') return;
        const v = item.getAttribute('data-vaccine');
        const d = item.getAttribute('data-date');
        const n = item.getAttribute('data-notes');
        doc.text(v, 10, y, {maxWidth: 55});
        doc.text(d, 70, y);
        doc.text(n, 120, y, {maxWidth: 80});
        y += 8;
        if (y > 270) { doc.addPage(); y = 20; }
    });
    doc.save('vaccination_history_animal_<?= $animalId ?>.pdf');
}
</script>
        <a href="#" id="backToProfileBtn" class="back-link"><i class="fas fa-arrow-left me-1"></i>Back to Animal Profile</a>
    </div>
</body>
<script>
// Animate tracker steps in sequence


// Detect QR code access via URL (?qr=1) and set back button target
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const animalId = <?= json_encode($animalId) ?>;
    const backBtn = document.getElementById('backToProfileBtn');
    if (params.get('qr') === '1') {
        backBtn.href = `view_animal_qr.php?id=${animalId}`;
    } else {
        backBtn.href = `view_animal.php?id=${animalId}`;
    }
});

// Add this to the existing JavaScript in vaccination_history.php
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Show success message if redirected from task completion
    if (urlParams.get('from_task') === '1') {
        // Create and show a success notification
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show';
        successAlert.style.position = 'fixed';
        successAlert.style.top = '20px';
        successAlert.style.right = '20px';
        successAlert.style.zIndex = '1050';
        successAlert.style.maxWidth = '300px';
        successAlert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> Vaccination task completed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(successAlert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (successAlert.parentNode) {
                successAlert.remove();
            }
        }, 5000);
    }
    
    // Your existing DOMContentLoaded code here...
    window.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.tracker-step.completed');
    steps.forEach((step, i) => {
        step.style.opacity = 0;
        setTimeout(() => {
            step.style.opacity = 1;
            step.classList.add('pop');
        }, 200 + i*180);
    });
});
});
</script>
</body>
</html>
