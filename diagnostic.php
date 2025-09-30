<?php
// Database Diagnostic Script for verify_vaccination.php
// Save this as diagnostic.php in your root directory

include 'includes/db.php';

echo "<h1>Database Diagnostic Report</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .error{color:red;} .success{color:green;}</style>";

try {
    echo "<h2>Database Connection Test</h2>";
    if (isset($pdo) && $pdo) {
        echo "<p class='success'>✓ Database connection established successfully</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed</p>";
        exit;
    }

    echo "<h2>Table Existence Check</h2>";

    $requiredTables = ['animals', 'vaccination_tasks', 'users'];
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p class='success'>✓ Table '$table' exists</p>";

                // Show table structure
                echo "<h3>Structure of '$table' table:</h3>";
                echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

                $stmt = $pdo->query("DESCRIBE $table");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>{$row['Field']}</td>";
                    echo "<td>{$row['Type']}</td>";
                    echo "<td>{$row['Null']}</td>";
                    echo "<td>{$row['Key']}</td>";
                    echo "<td>{$row['Default']}</td>";
                    echo "</tr>";
                }
                echo "</table>";

            } else {
                echo "<p class='error'>✗ Table '$table' does not exist</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error checking table '$table': " . $e->getMessage() . "</p>";
        }
    }

    echo "<h2>Sample Data Check</h2>";

    // Check if animals table has data
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM animals");
        $count = $stmt->fetch()['count'];
        echo "<p>Animals table has $count records</p>";

        if ($count > 0) {
            echo "<h3>Sample Animal Records:</h3>";
            $stmt = $pdo->query("SELECT id, qr_code, mark, type, breed FROM animals LIMIT 3");
            echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
            echo "<tr><th>ID</th><th>QR Code</th><th>Mark</th><th>Type</th><th>Breed</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr><td>{$row['id']}</td><td>{$row['qr_code']}</td><td>{$row['mark']}</td><td>{$row['type']}</td><td>{$row['breed']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking animals data: " . $e->getMessage() . "</p>";
    }

    // Check vaccination_tasks table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM vaccination_tasks WHERE status = 'completed'");
        $count = $stmt->fetch()['count'];
        echo "<p>Vaccination tasks table has $count completed records</p>";

        if ($count > 0) {
            echo "<h3>Sample Vaccination Records:</h3>";
            $stmt = $pdo->query("SELECT vt.*, a.qr_code, a.mark FROM vaccination_tasks vt JOIN animals a ON vt.animal_id = a.id WHERE vt.status = 'completed' LIMIT 3");
            echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
            echo "<tr><th>Animal ID</th><th>QR Code</th><th>Mark</th><th>Vaccine</th><th>Status</th><th>Completed Date</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr><td>{$row['animal_id']}</td><td>{$row['qr_code']}</td><td>{$row['mark']}</td><td>{$row['recommended_vaccine']}</td><td>{$row['status']}</td><td>{$row['completed_date']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking vaccination data: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>✗ General error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='verify_vaccination.php'>← Back to Vaccination Verification</a></p>";
?>
