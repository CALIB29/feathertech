<?php
// Database Migration Script: Fix vaccine_type column length
// Run this script to fix the "Data too long for column 'vaccine_type'" error

require_once 'includes/db.php';

echo "<h1>Database Migration: Fix vaccine_type Column</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;}</style>";

try {
    echo "<h2>Checking current schema...</h2>";

    // Check if animals table exists and has vaccine_type column
    $stmt = $pdo->query("DESCRIBE animals");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasVaccineType = false;
    $currentLength = 0;

    foreach ($columns as $column) {
        if ($column['Field'] === 'vaccine_type') {
            $hasVaccineType = true;
            $currentLength = $column['Type'];
            break;
        }
    }

    if (!$hasVaccineType) {
        echo "<div class='error'>❌ vaccine_type column not found in animals table</div>";
        echo "<p>The vaccine_type column doesn't exist. Please run the comprehensive schema update first.</p>";
        echo "<p><a href='comprehensive_schema_update.php'>→ Run Comprehensive Schema Update</a></p>";
        exit;
    }

    echo "<div class='success'>✅ vaccine_type column found: $currentLength</div>";

    // Check current length
    if (preg_match('/varchar\((\d+)\)/i', $currentLength, $matches)) {
        $length = (int)$matches[1];
        echo "<p>Current vaccine_type column length: <strong>$length characters</strong></p>";

        if ($length >= 255) {
            echo "<div class='success'>✅ Column length is already sufficient (≥255 characters)</div>";
            echo "<p>No migration needed.</p>";
            exit;
        }
    }

    echo "<h2>Performing migration...</h2>";

    // Increase vaccine_type column length to 255 characters
    $sql = "ALTER TABLE animals MODIFY COLUMN vaccine_type VARCHAR(255)";
    $pdo->exec($sql);

    echo "<div class='success'>✅ Successfully increased vaccine_type column length to 255 characters</div>";

    // Verify the change
    $stmt = $pdo->query("DESCRIBE animals");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        if ($column['Field'] === 'vaccine_type') {
            echo "<div class='success'>✅ Verified new column type: {$column['Type']}</div>";
            break;
        }
    }

    echo "<h2>Migration completed successfully!</h2>";
    echo "<p>The vaccine_type column can now store longer vaccine names.</p>";

    // Check for any existing data that might have been truncated
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM animals WHERE vaccine_type IS NOT NULL AND vaccine_type != ''");
    $result = $stmt->fetch();
    echo "<p>Total records with vaccine_type data: <strong>{$result['count']}</strong></p>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
    echo "<p>Error code: " . $e->getCode() . "</p>";

    if ($e->getCode() == '42S02') {
        echo "<p>The animals table doesn't exist. Please run the full schema setup first.</p>";
    } elseif ($e->getCode() == '42S21') {
        echo "<p>The vaccine_type column doesn't exist. The table structure needs to be updated.</p>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ General error: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Refresh your application</strong> - Clear browser cache if needed</li>";
echo "<li><strong>Test the vaccination update</strong> - Try updating a vaccination again</li>";
echo "<li><strong>Check error logs</strong> - Monitor for any remaining issues</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
