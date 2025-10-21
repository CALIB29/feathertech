<?php
// Comprehensive System Diagnostic Script
// Run this to identify issues with the vaccination system

echo "<h1>🔍 Vaccination System Diagnostic Report</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: #28a745; }
    .warning { color: #ffc107; }
    .error { color: #dc3545; }
    .info { color: #17a2b8; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .section h3 { margin-top: 0; color: #495057; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f8f9fa; }
    .status { font-weight: bold; }
</style>";

echo "<div class='section'>";
echo "<h3>📋 System Overview</h3>";
echo "<table>";
echo "<tr><th>Component</th><th>Status</th><th>Details</th></tr>";

// Check PHP version
$phpVersion = PHP_VERSION;
$phpStatus = version_compare($phpVersion, '7.4.0') >= 0 ? 'success' : 'warning';
echo "<tr><td>PHP Version</td><td class='$phpStatus'>$phpVersion</td><td>" . ($phpStatus == 'success' ? '✅ Compatible' : '⚠️ May have issues') . "</td></tr>";

// Check database connection
try {
    require_once 'includes/db.php';
    $testQuery = $pdo->query("SELECT 1");
    $dbStatus = $testQuery ? 'success' : 'error';
    echo "<tr><td>Database Connection</td><td class='$dbStatus'>Connected</td><td>✅ PDO connection successful</td></tr>";
} catch (Exception $e) {
    $dbStatus = 'error';
    echo "<tr><td>Database Connection</td><td class='error'>Failed</td><td>❌ {$e->getMessage()}</td></tr>";
}

// Check session
$sessionStatus = isset($_SESSION) ? 'success' : 'warning';
echo "<tr><td>Session Handling</td><td class='$sessionStatus'>Active</td><td>✅ Session management working</td></tr>";

// Check file upload permissions
$uploadDir = 'uploads/proofs/';
$uploadStatus = is_dir($uploadDir) ? (is_writable($uploadDir) ? 'success' : 'warning') : 'error';
$uploadDetails = is_dir($uploadDir) ? (is_writable($uploadDir) ? '✅ Directory exists and writable' : '⚠️ Directory exists but not writable') : '❌ Directory does not exist';
echo "<tr><td>File Upload Directory</td><td class='$uploadStatus'>$uploadDir</td><td>$uploadDetails</td></tr>";

echo "</table>";
echo "</div>";

// Check URL parameters
echo "<div class='section'>";
echo "<h3>🔗 URL Parameter Analysis</h3>";
echo "<p><strong>Current URL:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";
echo "<p><strong>Query String:</strong> " . htmlspecialchars($_SERVER['QUERY_STRING']) . "</p>";
echo "<p><strong>Request Method:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";

if (isset($_GET['task_id'])) {
    $taskId = $_GET['task_id'];
    echo "<div class='" . (is_numeric($taskId) ? 'success' : 'error') . "'>";
    echo "✅ task_id parameter found: <strong>$taskId</strong>";
    echo "</div>";
} else {
    echo "<div class='warning'>⚠️ No task_id parameter in URL</div>";
}
echo "</div>";

// Check form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div class='section'>";
    echo "<h3 class='" . (isset($_POST['task_id']) ? 'success' : 'error') . "'>📝 Form Submission Analysis</h3>";

    echo "<table>";
    echo "<tr><th>Parameter</th><th>Value</th><th>Status</th></tr>";

    $params = ['task_id', 'animal_id', 'message_id', 'notes', 'vaccine_type'];
    foreach ($params as $param) {
        $value = $_POST[$param] ?? 'Not provided';
        $status = 'info';
        if ($param === 'task_id' && (!isset($_POST[$param]) || empty($_POST[$param]))) {
            $status = 'error';
        } elseif ($param === 'animal_id' && (!isset($_POST[$param]) || empty($_POST[$param]))) {
            $status = 'warning';
        } elseif (isset($_POST[$param]) && !empty($_POST[$param])) {
            $status = 'success';
        }
        echo "<tr><td>$param</td><td>" . htmlspecialchars($value) . "</td><td class='$status'>" . getStatusIcon($status) . "</td></tr>";
    }
    echo "</table>";
    echo "</div>";
}

// Database integrity check
if (isset($pdo)) {
    echo "<div class='section'>";
    echo "<h3>🗄️ Database Integrity Check</h3>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Status</th><th>Count</th></tr>";

    $tables = [
        'vaccination_tasks' => 'SELECT COUNT(*) as count FROM vaccination_tasks',
        'animals' => 'SELECT COUNT(*) as count FROM animals',
        'users' => 'SELECT COUNT(*) as count FROM users',
        'notifications' => 'SELECT COUNT(*) as count FROM notifications'
    ];

    foreach ($tables as $table => $query) {
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            $status = $count > 0 ? 'success' : 'warning';
            echo "<tr><td>$table</td><td class='$status'>" . getStatusIcon($status) . "</td><td>$count records</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td class='error'>❌ Error</td><td>{$e->getMessage()}</td></tr>";
        }
    }
    echo "</table>";
    echo "</div>";
}

function getStatusIcon($status) {
    $icons = [
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'info' => 'ℹ️'
    ];
    return $icons[$status] ?? '❓';
}

// Recommendations
echo "<div class='section'>";
echo "<h3>💡 Recommendations</h3>";
echo "<ul>";
echo "<li><strong>Test the QR scanning flow:</strong> Go to vaccination_tasks.php → Click 'Scan QR to Complete' → Scan a QR code → Submit form</li>";
echo "<li><strong>Test direct upload:</strong> Go to vaccination_tasks.php → Click 'Upload Proof' → Select file → Submit</li>";
echo "<li><strong>Check server logs:</strong> Monitor PHP error logs for any issues</li>";
echo "<li><strong>Verify file permissions:</strong> Ensure upload directories are writable</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px;'>";
echo "<a href='vaccination_tasks.php' style='margin: 0 10px;'>← Vaccination Tasks</a>";
echo "<a href='dashboard.php' style='margin: 0 10px;'>🏠 Dashboard</a>";
echo "<a href='scan_qr.php?task_id=1' style='margin: 0 10px;'>📱 Test QR Scanner</a>";
echo "</div>";
?>
